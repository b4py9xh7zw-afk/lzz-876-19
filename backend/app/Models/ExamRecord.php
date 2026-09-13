<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'start_time',
        'end_time',
        'score',
        'status',
        'client_session_id',
        'last_boot_id',
        'device_fingerprint',
        'device_label',
        'last_heartbeat_at',
        'heartbeat_count',
        'client_clock_offset',
        'offline_seconds_total',
        'offline_event_count',
        'granted_extra_seconds',
        'review_reason',
        'review_requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_extra_seconds',
        'review_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'review_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
        'heartbeat_count' => 'integer',
        'client_clock_offset' => 'integer',
        'offline_seconds_total' => 'integer',
        'offline_event_count' => 'integer',
        'granted_extra_seconds' => 'integer',
        'review_extra_seconds' => 'integer',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
        self::STATUS_AWAITING_REVIEW => '待监考处理',
    ];

    public const REVIEW_REASON_OFFLINE_OVERTIME = 'offline_overtime';
    public const REVIEW_REASON_DEVICE_SWITCH = 'device_switch';
    public const REVIEW_REASON_HEARTBEAT_GAP = 'heartbeat_gap';
    public const REVIEW_REASON_CLOCK_MISMATCH = 'clock_mismatch';

    public const REVIEW_REASONS = [
        self::REVIEW_REASON_OFFLINE_OVERTIME => '断网超过允许时长',
        self::REVIEW_REASON_DEVICE_SWITCH => '换设备登录',
        self::REVIEW_REASON_HEARTBEAT_GAP => '心跳长时间缺失',
        self::REVIEW_REASON_CLOCK_MISMATCH => '本地时间与服务端偏差过大',
    ];

    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function drafts()
    {
        return $this->hasMany(ExamAnswerDraft::class, 'exam_record_id');
    }

    public function events()
    {
        return $this->hasMany(ExamSessionEvent::class, 'exam_record_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * 考试总可用时长（秒）= 试卷时长 + 监考批准的累计延时。
     */
    public function totalAllowedSeconds(): int
    {
        $paperMinutes = $this->examPaper?->total_time ?? 0;

        return $paperMinutes * 60 + (int) $this->granted_extra_seconds;
    }

    /**
     * 距截止还剩多少秒（服务端时钟，权威值），可能为负（已超时）。
     */
    public function remainingSeconds(?\Illuminate\Support\Carbon $now = null): int
    {
        $now = $now ?: now();
        $deadline = $this->start_time->copy()->addSeconds($this->totalAllowedSeconds());

        return max(0, (int) $deadline->diffInSeconds($now, false));
    }

    /**
     * 服务端视角是否已到交卷时间。
     */
    public function isExpired(?\Illuminate\Support\Carbon $now = null): bool
    {
        return $this->remainingSeconds($now) <= 0;
    }
}
