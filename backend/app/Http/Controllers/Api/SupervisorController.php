<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ExamSessionEvent;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupervisorController extends Controller
{
    public function __construct(protected ExamSessionService $sessions)
    {
    }

    /**
     * 监考工作台：待处理申请 + 进行中的考试。
     */
    public function index(Request $request)
    {
        $pending = ExamRecord::with(['user:id,username,real_name', 'examPaper:id,title,total_time'])
            ->where('status', ExamRecord::STATUS_AWAITING_REVIEW)
            ->orderByDesc('review_requested_at')
            ->get()
            ->map(fn ($r) => $this->formatRecord($r));

        $active = ExamRecord::with(['user:id,username,real_name', 'examPaper:id,title,total_time'])
            ->where('status', ExamRecord::STATUS_IN_PROGRESS)
            ->orderByDesc('last_heartbeat_at')
            ->limit(100)
            ->get()
            ->map(fn ($r) => $this->formatRecord($r));

        return response()->json([
            'pending' => $pending,
            'active' => $active,
            'config' => [
                'heartbeat_interval' => ExamSessionService::HEARTBEAT_INTERVAL,
                'offline_grace_seconds' => ExamSessionService::OFFLINE_AUTO_RESUME_SECONDS,
            ],
        ]);
    }

    /**
     * 某场考试的事件时间线（断网/刷新/换设备/心跳审计）。
     */
    public function events(Request $request, ExamRecord $record)
    {
        $events = $record->events()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (ExamSessionEvent $e) {
                return [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'event_type_label' => ExamSessionEvent::TYPE_LABELS[$e->event_type] ?? $e->event_type,
                    'event_label' => $e->event_label,
                    'risk_level' => $e->risk_level,
                    'risk_label' => ExamSessionEvent::RISK_LABELS[$e->risk_level] ?? $e->risk_level,
                    'is_cheat' => $e->is_cheat,
                    'resume_reason' => $e->resume_reason,
                    'ip' => $e->ip,
                    'client_offline_seconds' => $e->client_offline_seconds,
                    'server_gap_seconds' => $e->server_gap_seconds,
                    'device_label' => $e->device_label,
                    'metadata' => $e->metadata,
                    'server_event_time' => $e->server_event_time,
                ];
            });

        return response()->json([
            'record' => $this->formatRecord($record->loadMissing(['user:id,username,real_name', 'examPaper'])),
            'events' => $events,
        ]);
    }

    /**
     * 批准延时：恢复考试并增加可用时长。
     */
    public function approve(Request $request, ExamRecord $record)
    {
        $validator = Validator::make($request->all(), [
            'extra_minutes' => 'required|integer|min:1|max:300',
            'note' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($record->status !== ExamRecord::STATUS_AWAITING_REVIEW) {
            return response()->json(['message' => '该记录当前不在待处理状态'], 422);
        }

        $extraSeconds = (int) $request->extra_minutes * 60;

        // 若考试时间已耗尽，把开始时间整体顺延，保证批准的分钟数完整可用
        if ($record->isExpired()) {
            $overdue = -$record->remainingSeconds();
            $record->start_time = $record->start_time->copy()->addSeconds($overdue);
        }

        $record->update([
            'status' => ExamRecord::STATUS_IN_PROGRESS,
            'granted_extra_seconds' => (int) $record->granted_extra_seconds + $extraSeconds,
            'review_extra_seconds' => $extraSeconds,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_decision' => ExamRecord::DECISION_APPROVED,
            'review_note' => $request->input('note'),
            'last_heartbeat_at' => now(),
        ]);

        $this->sessions->logEvent($record, ExamSessionEvent::TYPE_REVIEW_DECIDED, [
            'event_label' => "监考批准延时 {$request->extra_minutes} 分钟",
            'risk_level' => ExamSessionEvent::RISK_LOW,
            'metadata' => [
                'extra_minutes' => (int) $request->extra_minutes,
                'note' => $request->input('note'),
            ],
        ]);

        return response()->json([
            'message' => '已批准延时',
            'record' => $this->formatRecord($record->fresh()),
            'timing' => $this->sessions->resumePayload($record->fresh())['timing'],
        ]);
    }

    /**
     * 驳回：用本地暂存的最新草稿自动交卷评分（不丢失学生答案），记录驳回决定。
     */
    public function reject(Request $request, ExamRecord $record)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($record->status !== ExamRecord::STATUS_AWAITING_REVIEW) {
            return response()->json(['message' => '该记录当前不在待处理状态'], 422);
        }

        $totalScore = 0;

        DB::transaction(function () use ($record, &$totalScore) {
            $questionMap = $record->examPaper->questions()->get()->keyBy('id');
            $drafts = $record->drafts()->get()->keyBy('question_id');

            foreach ($questionMap as $qid => $question) {
                $draft = $drafts->get($qid);
                if (!$draft || $draft->answer === null || $draft->answer === '') {
                    continue;
                }

                $isCorrect = $this->checkAnswer($question, (string) $draft->answer);
                $score = $isCorrect ? $question->pivot->score : 0;

                ExamRecordAnswer::create([
                    'exam_record_id' => $record->id,
                    'question_id' => $qid,
                    'answer' => $draft->answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);
                $totalScore += $score;
            }

            $record->update([
                'end_time' => now(),
                'score' => $totalScore,
                'status' => ExamRecord::STATUS_GRADED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_decision' => ExamRecord::DECISION_REJECTED,
                'review_note' => $request->input('note'),
            ]);
        });

        $this->sessions->logEvent($record, ExamSessionEvent::TYPE_REVIEW_DECIDED, [
            'event_label' => '监考驳回续考，已按暂存答案自动交卷',
            'risk_level' => ExamSessionEvent::RISK_HIGH,
            'metadata' => ['note' => $request->input('note'), 'score' => $totalScore],
        ]);

        return response()->json([
            'message' => '已驳回并按暂存答案自动交卷',
            'score' => $totalScore,
            'record' => $this->formatRecord($record->fresh()),
        ]);
    }

    /**
     * 监考人工标记/取消作弊标记（系统不会自动判定作弊）。
     */
    public function markCheat(Request $request, ExamSessionEvent $event)
    {
        $data = Validator::validate($request->all(), [
            'is_cheat' => 'required|boolean',
        ]);

        $event->update(['is_cheat' => $data['is_cheat']]);

        return response()->json(['message' => '已更新', 'event' => $event]);
    }

    protected function formatRecord(ExamRecord $r): array
    {
        $gap = $r->last_heartbeat_at
            ? max(0, (int) $r->last_heartbeat_at->diffInSeconds(now(), false))
            : null;

        return [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'user_name' => $r->user?->real_name ?: $r->user?->username,
            'username' => $r->user?->username,
            'exam_paper_id' => $r->exam_paper_id,
            'exam_paper_title' => $r->examPaper?->title,
            'total_time' => $r->examPaper?->total_time,
            'status' => $r->status,
            'status_label' => ExamRecord::STATUSES[$r->status] ?? $r->status,
            'start_time' => $r->start_time,
            'last_heartbeat_at' => $r->last_heartbeat_at,
            'heartbeat_gap_seconds' => $gap,
            'remaining_seconds' => $r->remainingSeconds(),
            'offline_seconds_total' => (int) $r->offline_seconds_total,
            'offline_event_count' => (int) $r->offline_event_count,
            'granted_extra_seconds' => (int) $r->granted_extra_seconds,
            'review_reason' => $r->review_reason,
            'review_reason_label' => $r->review_reason
                ? (ExamRecord::REVIEW_REASONS[$r->review_reason] ?? $r->review_reason)
                : null,
            'review_requested_at' => $r->review_requested_at,
            'review_decision' => $r->review_decision,
            'review_note' => $r->review_note,
            'device_label' => $r->device_label,
            'score' => $r->score,
            'drafts_count' => $r->drafts()->count(),
        ];
    }

    protected function checkAnswer($question, string $userAnswer): bool
    {
        $correctAnswer = $question->answer;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
            case 'fill_blank':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            case 'multiple_choice':
                $a = explode(',', strtoupper(trim($userAnswer)));
                $b = explode(',', strtoupper(trim($correctAnswer)));
                sort($a);
                sort($b);
                return $a === $b;
            default:
                return false;
        }
    }
}
