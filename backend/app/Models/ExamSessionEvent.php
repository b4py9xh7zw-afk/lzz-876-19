<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSessionEvent extends Model
{
    /**
     * 事件由服务端写入，仅维护 created_at，避免 Laravel 默认 updated_at 列缺失报错。
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'exam_record_id',
        'user_id',
        'event_type',
        'event_label',
        'risk_level',
        'is_cheat',
        'resume_reason',
        'client_session_id',
        'last_boot_id',
        'device_fingerprint',
        'device_label',
        'ip',
        'user_agent',
        'client_occurred_at',
        'server_event_time',
        'client_offline_seconds',
        'server_gap_seconds',
        'metadata',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'user_id' => 'integer',
        'is_cheat' => 'boolean',
        'client_occurred_at' => 'integer',
        'server_event_time' => 'datetime',
        'client_offline_seconds' => 'integer',
        'server_gap_seconds' => 'integer',
        'metadata' => 'array',
    ];

    public const TYPE_EXAM_STARTED = 'exam_started';
    public const TYPE_HEARTBEAT = 'heartbeat';
    public const TYPE_NETWORK_DOWN = 'network_down';
    public const TYPE_OFFLINE_RESUME = 'offline_resume';
    public const TYPE_PAGE_REFRESH = 'page_refresh';
    public const TYPE_DEVICE_SWITCH = 'device_switch';
    public const TYPE_SESSION_RESUME = 'session_resume';
    public const TYPE_REVIEW_REQUESTED = 'review_requested';
    public const TYPE_REVIEW_DECIDED = 'review_decided';
    public const TYPE_EXAM_SUBMITTED = 'exam_submitted';
    public const TYPE_ANOMALY = 'anomaly';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';

    public const RISK_LABELS = [
        self::RISK_LOW => '正常',
        self::RISK_MEDIUM => '需关注',
        self::RISK_HIGH => '高风险',
    ];

    public const TYPE_LABELS = [
        self::TYPE_EXAM_STARTED => '开始考试',
        self::TYPE_HEARTBEAT => '在线心跳',
        self::TYPE_NETWORK_DOWN => '网络中断',
        self::TYPE_OFFLINE_RESUME => '断网续考',
        self::TYPE_PAGE_REFRESH => '刷新/重开页面',
        self::TYPE_DEVICE_SWITCH => '换设备登录',
        self::TYPE_SESSION_RESUME => '会话恢复',
        self::TYPE_REVIEW_REQUESTED => '申请延时审核',
        self::TYPE_REVIEW_DECIDED => '监考审核结果',
        self::TYPE_EXAM_SUBMITTED => '提交答卷',
        self::TYPE_ANOMALY => '异常行为',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
