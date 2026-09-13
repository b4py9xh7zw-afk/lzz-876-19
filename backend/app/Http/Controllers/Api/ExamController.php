<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAnswerDraft;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ExamSessionEvent;
use App\Models\Question;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    public function __construct(protected ExamSessionService $sessions)
    {
    }

    public function index(Request $request)
    {
        $examPapers = ExamPaper::with('creator')
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'exam_papers' => $examPapers,
        ]);
    }

    /**
     * 开始考试（幂等）：已有进行中/待审核记录时直接返回续考状态。
     */
    public function start(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'client_session_id' => 'nullable|string|max:64',
            'boot_id' => 'nullable|string|max:64',
            'device_fingerprint' => 'nullable|string|max:128',
            'device_label' => 'nullable|string|max:255',
            'client_now' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => $existing->status === ExamRecord::STATUS_AWAITING_REVIEW
                    ? '考试待监考处理'
                    : '您已经开始这场考试',
                'resumed' => true,
            ] + $this->sessions->resumePayload($existing));
        }

        $ctx = $this->sessions->context($request);
        $clockOffset = 0;
        if ($ctx['client_now'] > 0) {
            $clockOffset = (int) \Illuminate\Support\Carbon::createFromTimestampMs($ctx['client_now'])
                ->diffInSeconds(now(), false);
        }

        $record = ExamRecord::create([
            'user_id' => $request->user()->id,
            'exam_paper_id' => $examPaper->id,
            'start_time' => now(),
            'status' => ExamRecord::STATUS_IN_PROGRESS,
            'client_session_id' => $ctx['client_session_id'] ?: null,
            'last_boot_id' => $ctx['boot_id'] ?: null,
            'device_fingerprint' => $ctx['device_fingerprint'] ?: null,
            'device_label' => $ctx['device_label'] ?: null,
            'last_heartbeat_at' => now(),
            'client_clock_offset' => $clockOffset,
        ]);

        $this->sessions->logEvent($record, ExamSessionEvent::TYPE_EXAM_STARTED, [
            'client_session_id' => $ctx['client_session_id'] ?: null,
            'last_boot_id' => $ctx['boot_id'] ?: null,
            'device_fingerprint' => $ctx['device_fingerprint'] ?: null,
            'device_label' => $ctx['device_label'] ?: null,
            'client_occurred_at' => $ctx['client_now'] ?: null,
        ]);

        return response()->json([
            'message' => '考试开始',
            'resumed' => false,
        ] + $this->sessions->resumePayload($record->fresh()));
    }

    /**
     * 兼容旧前端的取题接口：返回续考所需的完整状态。
     */
    public function getQuestions(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->firstOrFail();

        return response()->json($this->sessions->resumePayload($record));
    }

    /**
     * 续考：页面加载/网络恢复/换设备时调用，后端区分真实断网、刷新页面与换设备登录。
     */
    public function resume(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'client_session_id' => 'nullable|string|max:64',
            'boot_id' => 'nullable|string|max:64',
            'device_fingerprint' => 'nullable|string|max:128',
            'device_label' => 'nullable|string|max:255',
            'client_now' => 'nullable|integer',
            'reason' => 'nullable|string|in:network_down,page_refresh,device_switch',
            'offline_seconds' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->first();

        if (!$record) {
            return response()->json(['message' => '未找到进行中的考试，请先开始考试'], 404);
        }

        $ctx = $this->sessions->context($request);
        $gap = $this->sessions->heartbeatGap($record);

        // 待审核状态只返回状态（前端轮询审核结果），不重复记事件
        if ($record->status === ExamRecord::STATUS_AWAITING_REVIEW) {
            return response()->json([
                'message' => '考试待监考处理',
                'resumed' => false,
            ] + $this->sessions->resumePayload($record));
        }

        [$type, $reason, $risk, $flags, $gap, $clockOffset] =
            $this->sessions->classifyResume($record, $ctx, $gap);

        // 仅在有实际中断迹象或换设备/刷新时记录事件，避免普通轮询刷屏
        $shouldLog = $type !== ExamSessionEvent::TYPE_SESSION_RESUME
            || ($gap !== null && $gap > ExamSessionService::HEARTBEAT_INTERVAL)
            || $ctx['reason'] !== '';

        if ($shouldLog) {
            $this->sessions->logEvent($record, $type, [
                'event_label' => ExamSessionEvent::TYPE_LABELS[$type]
                    . ($flags ? '（' . implode('；', $flags) . '）' : ''),
                'risk_level' => $risk,
                'resume_reason' => $reason,
                'client_session_id' => $ctx['client_session_id'] ?: null,
                'last_boot_id' => $ctx['boot_id'] ?: null,
                'device_fingerprint' => $ctx['device_fingerprint'] ?: null,
                'device_label' => $ctx['device_label'] ?: null,
                'client_occurred_at' => $ctx['client_now'] ?: null,
                'client_offline_seconds' => $request->input('offline_seconds'),
                'server_gap_seconds' => $gap,
                'metadata' => ['flags' => $flags],
            ]);
        }

        // 真实断网：把心跳缺口计入累计断网时长
        if ($type === ExamSessionEvent::TYPE_OFFLINE_RESUME && $gap !== null
            && $gap > ExamSessionService::HEARTBEAT_INTERVAL + ExamSessionService::HEARTBEAT_GRACE) {
            $offlineSeconds = $gap - ExamSessionService::HEARTBEAT_INTERVAL;
            $record->increment('offline_seconds_total', $offlineSeconds);
            $record->increment('offline_event_count');
        }

        // 刷新/换设备：更新当前会话指纹（后续心跳以新会话为准）
        $update = [
            'last_heartbeat_at' => now(),
            'client_session_id' => $ctx['client_session_id'] ?: $record->client_session_id,
            'last_boot_id' => $ctx['boot_id'] ?: $record->last_boot_id,
            'client_clock_offset' => $clockOffset ?? $record->client_clock_offset,
        ];
        if ($ctx['device_fingerprint']) {
            $update['device_fingerprint'] = $ctx['device_fingerprint'];
            $update['device_label'] = $ctx['device_label'] ?: $record->device_label;
        }
        $record->update($update);

        // 超过允许的断网时长（或高风险换设备）→ 交监考老师决定是否延时
        $needReview = null;
        $effectiveGap = is_int($gap) ? $gap : 0;
        if ($type === ExamSessionEvent::TYPE_OFFLINE_RESUME
            && $effectiveGap > ExamSessionService::HEARTBEAT_INTERVAL + ExamSessionService::OFFLINE_AUTO_RESUME_SECONDS) {
            $needReview = ExamRecord::REVIEW_REASON_OFFLINE_OVERTIME;
        } elseif ($type === ExamSessionEvent::TYPE_DEVICE_SWITCH && $risk === ExamSessionEvent::RISK_HIGH) {
            $needReview = ExamRecord::REVIEW_REASON_DEVICE_SWITCH;
        } elseif (abs($clockOffset ?? 0) > ExamSessionService::CLOCK_TOLERANCE_SECONDS) {
            $needReview = ExamRecord::REVIEW_REASON_CLOCK_MISMATCH;
        }

        if ($needReview) {
            $this->sessions->requestReview($record, $needReview, [
                'detected_type' => $type,
                'server_gap_seconds' => $gap,
                'client_offline_seconds' => $request->input('offline_seconds'),
                'flags' => $flags,
            ]);
        }

        $payload = $this->sessions->resumePayload($record->fresh());
        $payload['resumed'] = true;
        $payload['resume'] = [
            'detected_type' => $type,
            'detected_type_label' => ExamSessionEvent::TYPE_LABELS[$type] ?? $type,
            'reason' => $reason,
            'risk_level' => $risk,
            'flags' => $flags,
            'server_gap_seconds' => $gap,
            'need_review' => $needReview !== null,
        ];

        return response()->json($payload);
    }

    /**
     * 心跳：在线期间定时上报；服务端用心跳缺口识别真实断网。
     */
    public function heartbeat(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'client_session_id' => 'nullable|string|max:64',
            'boot_id' => 'nullable|string|max:64',
            'device_fingerprint' => 'nullable|string|max:128',
            'device_label' => 'nullable|string|max:255',
            'client_now' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->firstOrFail();

        if ($record->status === ExamRecord::STATUS_AWAITING_REVIEW) {
            return response()->json([
                'ok' => true,
                'awaiting_review' => true,
                'server_now' => now()->getTimestampMs(),
            ] + collect($this->sessions->resumePayload($record))->only('timing', 'review')->toArray());
        }

        $ctx = $this->sessions->context($request);
        $result = $this->sessions->heartbeat($record, $ctx);
        $record->refresh();

        // 心跳缺口超过自动续考时限（如休眠/长时间卡顿恢复）：同样交监考处理
        $needReview = $result['offline_detected']
            && $result['offline_seconds'] > ExamSessionService::OFFLINE_AUTO_RESUME_SECONDS
            && $record->status === ExamRecord::STATUS_IN_PROGRESS;

        if ($needReview) {
            $this->sessions->requestReview($record, ExamRecord::REVIEW_REASON_HEARTBEAT_GAP, [
                'server_gap_seconds' => $result['gap_seconds'],
                'offline_seconds' => $result['offline_seconds'],
            ]);
            $record->refresh();
        }

        $payload = $this->sessions->resumePayload($record);

        return response()->json([
            'ok' => true,
            'awaiting_review' => $record->status === ExamRecord::STATUS_AWAITING_REVIEW,
            'server_now' => $result['server_now'],
            'gap_seconds' => $result['gap_seconds'],
            'offline_detected' => $result['offline_detected'],
            'offline_seconds' => $result['offline_seconds'],
            'clock_offset' => $result['clock_offset'],
            'timing' => $payload['timing'],
            'review' => $payload['review'],
        ]);
    }

    /**
     * 保存答题草稿（在线时定时/答题即存；断网恢复后补传）。
     */
    public function saveDrafts(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'drafts' => 'required|array',
            'drafts.*.question_id' => 'required|integer|exists:questions,id',
            'drafts.*.answer' => 'nullable|string',
            'drafts.*.status' => 'nullable|string|in:unanswered,answered,flagged',
            'drafts.*.updated_client_at' => 'nullable|integer',
            'client_session_id' => 'nullable|string|max:64',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->firstOrFail();

        $questionIds = $examPaper->questions()->pluck('questions.id')->all();
        $saved = 0;

        foreach ($request->drafts as $draft) {
            if (!in_array((int) $draft['question_id'], $questionIds, true)) {
                continue;
            }

            $answer = $draft['answer'] ?? '';
            $status = $draft['status'] ?? ($answer !== '' ? 'answered' : 'unanswered');
            $clientAt = $draft['updated_client_at'] ?? null;

            $existing = ExamAnswerDraft::where('exam_record_id', $record->id)
                ->where('question_id', $draft['question_id'])
                ->first();

            // 以客户端修改时间为准，旧数据不覆盖新数据
            if ($existing && $clientAt && $existing->updated_client_at
                && (int) $clientAt < (int) $existing->updated_client_at) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'answer' => $answer,
                    'status' => $status,
                    'updated_client_at' => $clientAt,
                    'synced_at' => now(),
                ]);
            } else {
                ExamAnswerDraft::create([
                    'exam_record_id' => $record->id,
                    'question_id' => $draft['question_id'],
                    'answer' => $answer,
                    'status' => $status,
                    'updated_client_at' => $clientAt,
                    'synced_at' => now(),
                ]);
            }
            $saved++;
        }

        return response()->json([
            'message' => '草稿已保存',
            'saved' => $saved,
            'server_now' => now()->getTimestampMs(),
        ]);
    }

    /**
     * 提交答卷。支持携带断网期间本地暂存的提交时间；草稿作为兜底答案。
     */
    public function submit(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'present|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'present|string',
            'client_submitted_at' => 'nullable|integer',
            'offline_seconds' => 'nullable|integer|min:0',
            'client_session_id' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->whereIn('status', [ExamRecord::STATUS_IN_PROGRESS, ExamRecord::STATUS_AWAITING_REVIEW])
            ->firstOrFail();

        if ($record->status === ExamRecord::STATUS_AWAITING_REVIEW) {
            return response()->json([
                'message' => '该场考试存在待监考处理的异常，请等待监考老师处理后再交卷',
                'awaiting_review' => true,
            ], 409);
        }

        // 以试卷题目为准，提交答案与服务端草稿合并（提交优先，缺失题目用草稿兜底）
        $questionMap = $examPaper->questions()->get()->keyBy('id');
        $submitted = collect($request->answers)->keyBy('question_id');
        $drafts = $record->drafts()->get()->keyBy('question_id');

        $totalScore = 0;

        DB::transaction(function () use ($record, $questionMap, $submitted, $drafts, &$totalScore) {
            foreach ($questionMap as $qid => $question) {
                $answer = null;
                if (isset($submitted[$qid])) {
                    $answer = (string) $submitted[$qid]['answer'];
                } elseif (isset($drafts[$qid]) && $drafts[$qid]->answer !== null && $drafts[$qid]->answer !== '') {
                    $answer = (string) $drafts[$qid]->answer;
                }

                if ($answer === null || $answer === '') {
                    continue;
                }

                $isCorrect = $this->checkAnswer($question, $answer);
                $score = $isCorrect ? $question->pivot->score : 0;

                ExamRecordAnswer::create([
                    'exam_record_id' => $record->id,
                    'question_id' => $qid,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);

                $totalScore += $score;
            }

            $record->update([
                'end_time' => now(),
                'score' => $totalScore,
                'status' => ExamRecord::STATUS_GRADED,
            ]);
        });

        $this->sessions->logEvent($record, ExamSessionEvent::TYPE_EXAM_SUBMITTED, [
            'event_label' => '提交答卷'
                . ($request->input('offline_seconds') ? "（自报断网 {$request->input('offline_seconds')} 秒）" : ''),
            'client_occurred_at' => $request->input('client_submitted_at'),
            'client_offline_seconds' => $request->input('offline_seconds'),
            'metadata' => ['score' => $totalScore],
        ]);

        return response()->json([
            'message' => '提交成功',
            'score' => $totalScore,
            'exam_record' => $record->load('answers'),
        ]);
    }

    public function myRecords(Request $request)
    {
        $records = ExamRecord::with('examPaper')
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'records' => $records,
        ]);
    }

    public function showRecord(Request $request, ExamRecord $record)
    {
        if ($record->user_id !== $request->user()->id && !$request->user()->isTeacher()) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load(['examPaper.questions', 'answers.question']);

        return response()->json([
            'record' => $record,
        ]);
    }

    protected function checkAnswer(Question $question, string $userAnswer): bool
    {
        $correctAnswer = $question->answer;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            case 'multiple_choice':
                $userAnswers = explode(',', strtoupper(trim($userAnswer)));
                $correctAnswers = explode(',', strtoupper(trim($correctAnswer)));
                sort($userAnswers);
                sort($correctAnswers);
                return $userAnswers === $correctAnswers;
            case 'fill_blank':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            default:
                return false;
        }
    }
}
