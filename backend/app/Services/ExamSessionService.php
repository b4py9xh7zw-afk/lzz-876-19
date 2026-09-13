<?php

namespace App\Services;

use App\Models\ExamRecord;
use App\Models\ExamSessionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 考试会话服务：心跳、事件分类（真实断网 / 刷新页面 / 换设备）、监考审核流转。
 *
 * 分类依据（多信号交叉，不做自动作弊判定）：
 *  - device_fingerprint：持久化设备 ID，换设备时变化；
 *  - client_session_id：标签页会话 ID（sessionStorage），刷新后仍在、关闭重开/换设备后变化；
 *  - last_boot_id：每次页面加载新生成的一次性 ID，刷新/重开必然变化；
 *  - 心跳缺口：服务端距上次心跳的实际秒数，反映"真实断网"时长；
 *  - 客户端自报原因（network_down / page_refresh / device_switch）作为参考，服务端以客观信号复核。
 */
class ExamSessionService
{
    /** 心跳间隔（秒），前端按此频率上报。 */
    public const HEARTBEAT_INTERVAL = 15;

    /** 心跳缺口容忍秒数：超过间隔的 2 倍才认为发生过中断。 */
    public const HEARTBEAT_GRACE = 30;

    /** 断网自动续考宽限时长（秒）：超过则交监考老师决定是否延时。 */
    public const OFFLINE_AUTO_RESUME_SECONDS = 180;

    /** 客户端时钟允许偏差（秒），超过则标记异常。 */
    public const CLOCK_TOLERANCE_SECONDS = 60;

    public function serverNow(): Carbon
    {
        return now();
    }

    /**
     * 从请求中提取会话上下文。
     */
    public function context(Request $request): array
    {
        return [
            'client_session_id' => mb_substr((string) $request->input('client_session_id', ''), 0, 64),
            'boot_id' => mb_substr((string) $request->input('boot_id', ''), 0, 64),
            'device_fingerprint' => mb_substr((string) $request->input('device_fingerprint', ''), 0, 128),
            'device_label' => mb_substr((string) $request->input('device_label', ''), 0, 255),
            'client_now' => (int) $request->input('client_now', 0),
            'reason' => mb_substr((string) $request->input('reason', ''), 0, 20),
        ];
    }

    /**
     * 记录一条会话事件。
     */
    public function logEvent(ExamRecord $record, string $type, array $attrs = []): ExamSessionEvent
    {
        $request = request();

        $event = new ExamSessionEvent([
            'exam_record_id' => $record->id,
            'user_id' => $record->user_id,
            'event_type' => $type,
            'event_label' => $attrs['event_label'] ?? (ExamSessionEvent::TYPE_LABELS[$type] ?? $type),
            'risk_level' => $attrs['risk_level'] ?? ExamSessionEvent::RISK_LOW,
            'is_cheat' => $attrs['is_cheat'] ?? false,
            'resume_reason' => $attrs['resume_reason'] ?? null,
            'client_session_id' => $attrs['client_session_id'] ?? null,
            'last_boot_id' => $attrs['last_boot_id'] ?? null,
            'device_fingerprint' => $attrs['device_fingerprint'] ?? null,
            'device_label' => $attrs['device_label'] ?? null,
            'ip' => $attrs['ip'] ?? ($request?->ip()),
            'user_agent' => $attrs['user_agent'] ?? mb_substr((string) $request?->userAgent(), 0, 500),
            'client_occurred_at' => $attrs['client_occurred_at'] ?? null,
            'server_event_time' => now(),
            'client_offline_seconds' => $attrs['client_offline_seconds'] ?? null,
            'server_gap_seconds' => $attrs['server_gap_seconds'] ?? null,
            'metadata' => $attrs['metadata'] ?? null,
        ]);
        $event->save();

        return $event;
    }

    /**
     * 计算距上次心跳的缺口（秒）。无心跳记录时返回 null。
     */
    public function heartbeatGap(ExamRecord $record): ?int
    {
        if (!$record->last_heartbeat_at) {
            return null;
        }

        return max(0, (int) $record->last_heartbeat_at->diffInSeconds(now(), false));
    }

    /**
     * 处理心跳。返回心跳缺口信息，并在检测到真实断网时记录 network_down 事件。
     */
    public function heartbeat(ExamRecord $record, array $ctx): array
    {
        $gap = $this->heartbeatGap($record);

        $update = [
            'last_heartbeat_at' => now(),
            'heartbeat_count' => $record->heartbeat_count + 1,
            'client_session_id' => $ctx['client_session_id'] ?: $record->client_session_id,
            'last_boot_id' => $ctx['boot_id'] ?: $record->last_boot_id,
        ];

        if ($ctx['device_fingerprint']) {
            $update['device_fingerprint'] = $ctx['device_fingerprint'];
            $update['device_label'] = $ctx['device_label'] ?: $record->device_label;
        }

        $clockOffset = null;
        if ($ctx['client_now'] > 0) {
            $clientAt = Carbon::createFromTimestampMs($ctx['client_now']);
            $clockOffset = (int) $clientAt->diffInSeconds(now(), false);
            $update['client_clock_offset'] = $clockOffset;
        }

        $record->update($update);

        $offlineDetected = false;
        $offlineSeconds = 0;
        if ($gap !== null && $gap > self::HEARTBEAT_INTERVAL + self::HEARTBEAT_GRACE) {
            // 心跳缺口超过容忍线：客观上发生过网络中断
            $offlineSeconds = $gap - self::HEARTBEAT_INTERVAL;
            $record->increment('offline_seconds_total', $offlineSeconds);
            $record->increment('offline_event_count');

            $risk = ExamSessionEvent::RISK_LOW;
            $label = "网络中断约 {$offlineSeconds} 秒后自动恢复";
            if ($offlineSeconds > self::OFFLINE_AUTO_RESUME_SECONDS) {
                $risk = ExamSessionEvent::RISK_HIGH;
                $label = "网络中断约 {$offlineSeconds} 秒，超过自动续考时限";
            } elseif ($offlineSeconds > self::HEARTBEAT_GRACE * 2) {
                $risk = ExamSessionEvent::RISK_MEDIUM;
            }

            $this->logEvent($record, ExamSessionEvent::TYPE_NETWORK_DOWN, [
                'event_label' => $label,
                'risk_level' => $risk,
                'resume_reason' => 'network_down',
                'client_session_id' => $ctx['client_session_id'] ?: null,
                'last_boot_id' => $ctx['boot_id'] ?: null,
                'device_fingerprint' => $ctx['device_fingerprint'] ?: null,
                'device_label' => $ctx['device_label'] ?: null,
                'client_occurred_at' => $ctx['client_now'] ?: null,
                'server_gap_seconds' => $gap,
            ]);
            $offlineDetected = true;
        }

        return [
            'server_now' => now()->getTimestampMs(),
            'gap_seconds' => $gap,
            'offline_detected' => $offlineDetected,
            'offline_seconds' => $offlineSeconds,
            'clock_offset' => $clockOffset,
        ];
    }

    /**
     * 续考场景分类。
     *
     * 返回: [type, reason, risk, flags]
     *  - device_switch：设备指纹与开考设备不一致（客观信号，优先于自报）；
     *  - page_refresh：同一设备、同一标签页会话，仅 boot_id 变化；
     *  - offline_resume：心跳缺口超过容忍线（真实断网）；
     *  - session_resume：同设备、无明显心跳缺口（浏览器后台/短暂切换等）。
     */
    public function classifyResume(ExamRecord $record, array $ctx, ?int $gap): array
    {
        $flags = [];
        $isDeviceSwitch = $ctx['device_fingerprint']
            && $record->device_fingerprint
            && $ctx['device_fingerprint'] !== $record->device_fingerprint;

        $sameTabSession = $ctx['client_session_id']
            && $record->client_session_id
            && $ctx['client_session_id'] === $record->client_session_id;

        $bootChanged = $ctx['boot_id']
            && $record->last_boot_id
            && $ctx['boot_id'] !== $record->last_boot_id;

        $reported = $ctx['reason'];
        if (!in_array($reported, ['network_down', 'page_refresh', 'device_switch'], true)) {
            $reported = null;
        }

        $gapOffline = $gap !== null && $gap > self::HEARTBEAT_INTERVAL + self::HEARTBEAT_GRACE;

        if ($isDeviceSwitch) {
            $type = ExamSessionEvent::TYPE_DEVICE_SWITCH;
            $reason = 'device_switch';
            $risk = ExamSessionEvent::RISK_MEDIUM;
            $flags[] = '设备指纹与开考设备不一致';
            if ($reported && $reported !== 'device_switch') {
                $flags[] = "客户端自报为 {$reported}，与客观信号不一致";
                $risk = ExamSessionEvent::RISK_HIGH;
            }
        } elseif ($gapOffline) {
            $type = ExamSessionEvent::TYPE_OFFLINE_RESUME;
            $reason = 'network_down';
            $risk = $gap > self::OFFLINE_AUTO_RESUME_SECONDS
                ? ExamSessionEvent::RISK_HIGH
                : ExamSessionEvent::RISK_LOW;
            if ($reported && $reported !== 'network_down') {
                $flags[] = "客户端自报为 {$reported}，但心跳缺口 {$gap} 秒，按真实断网处理";
            }
            // 自报断网时长与服务端心跳缺口交叉校验
            if ($ctx['client_now'] > 0) {
                $flags[] = "心跳缺口 {$gap} 秒";
            }
        } elseif ($sameTabSession && $bootChanged) {
            $type = ExamSessionEvent::TYPE_PAGE_REFRESH;
            $reason = 'page_refresh';
            $risk = ExamSessionEvent::RISK_LOW;
            $flags[] = '同设备同标签页重新加载，答案已本地暂存';
        } else {
            $type = ExamSessionEvent::TYPE_SESSION_RESUME;
            $reason = $sameTabSession ? 'session_resume' : ($reported ?: 'session_resume');
            $risk = ExamSessionEvent::RISK_LOW;
        }

        // 时钟偏差校验
        $clockOffset = $record->client_clock_offset;
        if ($ctx['client_now'] > 0) {
            $clientAt = Carbon::createFromTimestampMs($ctx['client_now']);
            $clockOffset = (int) $clientAt->diffInSeconds(now(), false);
            if (abs($clockOffset) > self::CLOCK_TOLERANCE_SECONDS) {
                $flags[] = "本地时间与服务端偏差 {$clockOffset} 秒";
                if ($risk === ExamSessionEvent::RISK_LOW) {
                    $risk = ExamSessionEvent::RISK_MEDIUM;
                }
            }
        }

        return [$type, $reason, $risk, $flags, $gap, $clockOffset];
    }

    /**
     * 把考试记录置为"待监考处理"。
     */
    public function requestReview(ExamRecord $record, string $reason, array $extra = []): void
    {
        if ($record->status !== ExamRecord::STATUS_AWAITING_REVIEW) {
            $record->update([
                'status' => ExamRecord::STATUS_AWAITING_REVIEW,
                'review_reason' => $reason,
                'review_requested_at' => now(),
                'review_decision' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        }

        $this->logEvent($record, ExamSessionEvent::TYPE_REVIEW_REQUESTED, [
            'event_label' => ExamRecord::REVIEW_REASONS[$reason] ?? $reason,
            'risk_level' => ExamSessionEvent::RISK_HIGH,
            'metadata' => $extra,
        ]);
    }

    /**
     * 组装前端续考/开考所需的考试状态。
     */
    public function resumePayload(ExamRecord $record): array
    {
        $record->loadMissing('examPaper.questions', 'drafts');
        $paper = $record->examPaper;

        $questions = $paper->questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });

        $drafts = $record->drafts->mapWithKeys(function ($d) {
            return [$d->question_id => [
                'answer' => $d->answer,
                'status' => $d->status,
                'updated_client_at' => $d->updated_client_at,
            ]];
        });

        return [
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $paper->id,
                'title' => $paper->title,
                'total_time' => $paper->total_time,
                'total_score' => $paper->total_score,
            ],
            'questions' => $questions,
            'drafts' => $drafts,
            'timing' => [
                'server_now' => now()->getTimestampMs(),
                'start_time' => $record->start_time->getTimestampMs(),
                'deadline' => $record->start_time->copy()
                    ->addSeconds($record->totalAllowedSeconds())->getTimestampMs(),
                'remaining_seconds' => $record->remainingSeconds(),
                'total_allowed_seconds' => $record->totalAllowedSeconds(),
                'granted_extra_seconds' => (int) $record->granted_extra_seconds,
                'offline_seconds_total' => (int) $record->offline_seconds_total,
                'heartbeat_interval' => self::HEARTBEAT_INTERVAL,
                'offline_grace_seconds' => self::OFFLINE_AUTO_RESUME_SECONDS,
                'expired' => $record->isExpired(),
            ],
            'review' => [
                'status' => $record->status,
                'reason' => $record->review_reason,
                'reason_label' => $record->review_reason
                    ? (ExamRecord::REVIEW_REASONS[$record->review_reason] ?? $record->review_reason)
                    : null,
                'decision' => $record->review_decision,
                'note' => $record->review_note,
            ],
        ];
    }
}
