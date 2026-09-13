<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 断网续考保护
 *
 * 该迁移是幂等的：docker-compose 首次初始化的库已包含新结构时会自动跳过，
 * 已存在的旧库则补齐字段与新表。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exam_answer_drafts')) {
            Schema::create('exam_answer_drafts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
                $table->unsignedBigInteger('question_id')->comment('题目ID');
                $table->text('answer')->nullable()->comment('暂存答案');
                $table->string('status', 20)->default('unanswered')->comment('题目状态: unanswered/answered/flagged');
                $table->unsignedBigInteger('updated_client_at')->nullable()->comment('客户端最后修改时间(ms)');
                $table->timestamp('synced_at')->nullable()->comment('最近服务端同步时间');
                $table->timestamps();

                $table->unique(['exam_record_id', 'question_id'], 'uk_draft_record_question');
                $table->index('exam_record_id', 'idx_draft_record');
            });

            DB::statement("ALTER TABLE exam_answer_drafts COMMENT='答题草稿(断网暂存)表'");
        }

        if (!Schema::hasTable('exam_session_events')) {
            Schema::create('exam_session_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
                $table->unsignedBigInteger('user_id')->comment('考生ID');
                $table->string('event_type', 30)->comment('事件类型');
                $table->string('event_label', 100)->nullable()->comment('事件说明');
                $table->string('risk_level', 10)->default('low')->comment('风险等级: low/medium/high');
                $table->boolean('is_cheat')->default(0)->comment('是否作弊: 系统不自动判定, 由监考人工标记');
                $table->string('resume_reason', 20)->nullable()->comment('客户端自报续考原因');
                $table->string('client_session_id', 64)->nullable()->comment('标签页会话ID');
                $table->string('last_boot_id', 64)->nullable()->comment('上次页面启动ID');
                $table->string('device_fingerprint', 128)->nullable()->comment('设备指纹');
                $table->string('device_label', 255)->nullable()->comment('设备说明');
                $table->string('ip', 45)->nullable()->comment('IP');
                $table->string('user_agent', 500)->nullable()->comment('UA');
                $table->unsignedBigInteger('client_occurred_at')->nullable()->comment('客户端事件时间(ms)');
                $table->timestamp('server_event_time')->useCurrent()->comment('服务端事件时间');
                $table->integer('client_offline_seconds')->nullable()->comment('客户端自报断网时长(秒)');
                $table->integer('server_gap_seconds')->nullable()->comment('服务端心跳缺口(秒)');
                $table->json('metadata')->nullable()->comment('附加信息');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['exam_record_id', 'id'], 'idx_event_record');
                $table->index(['user_id', 'id'], 'idx_event_user');
                $table->index(['event_type', 'id'], 'idx_event_type');
                $table->index(['risk_level', 'id'], 'idx_event_risk');
            });

            DB::statement("ALTER TABLE exam_session_events COMMENT='考试会话事件表(断网/刷新/换设备审计)'");
        }

        if (Schema::hasTable('exam_records') && !Schema::hasColumn('exam_records', 'client_session_id')) {
            Schema::table('exam_records', function (Blueprint $table) {
                $table->string('client_session_id', 64)->nullable()->after('status')->comment('当前标签页会话ID');
                $table->string('last_boot_id', 64)->nullable()->after('client_session_id')->comment('最近页面启动ID');
                $table->string('device_fingerprint', 128)->nullable()->after('last_boot_id')->comment('当前设备指纹');
                $table->string('device_label', 255)->nullable()->after('device_fingerprint')->comment('当前设备说明');
                $table->timestamp('last_heartbeat_at')->nullable()->after('device_label')->comment('最近心跳时间');
                $table->unsignedInteger('heartbeat_count')->default(0)->after('last_heartbeat_at')->comment('心跳次数');
                $table->integer('client_clock_offset')->default(0)->after('heartbeat_count')->comment('客户端时钟偏差(秒, 服务端-客户端)');
                $table->unsignedInteger('offline_seconds_total')->default(0)->after('client_clock_offset')->comment('累计断网时长(秒)');
                $table->unsignedInteger('offline_event_count')->default(0)->after('offline_seconds_total')->comment('断网续考次数');
                $table->unsignedInteger('granted_extra_seconds')->default(0)->after('offline_event_count')->comment('监考批准的延时(秒)');
                $table->string('review_reason', 50)->nullable()->after('granted_extra_seconds')->comment('待审核原因');
                $table->timestamp('review_requested_at')->nullable()->after('review_reason')->comment('提交监考时间');
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_requested_at')->comment('审核监考ID');
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by')->comment('审核时间');
                $table->string('review_decision', 20)->nullable()->after('reviewed_at')->comment('审核决定: approved/rejected');
                $table->unsignedInteger('review_extra_seconds')->nullable()->after('review_decision')->comment('本次批准延时(秒)');
                $table->text('review_note')->nullable()->after('review_extra_seconds')->comment('审核备注');

                $table->index('last_heartbeat_at', 'idx_record_heartbeat');
                $table->index('review_reason', 'idx_record_review');
            });

            DB::statement("ALTER TABLE exam_records MODIFY status ENUM('in_progress','submitted','graded','awaiting_review') DEFAULT 'in_progress' COMMENT '状态'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exam_records') && Schema::hasColumn('exam_records', 'client_session_id')) {
            Schema::table('exam_records', function (Blueprint $table) {
                $table->dropColumn([
                    'client_session_id', 'last_boot_id', 'device_fingerprint', 'device_label',
                    'last_heartbeat_at', 'heartbeat_count', 'client_clock_offset',
                    'offline_seconds_total', 'offline_event_count', 'granted_extra_seconds',
                    'review_reason', 'review_requested_at', 'reviewed_by', 'reviewed_at',
                    'review_decision', 'review_extra_seconds', 'review_note',
                ]);
            });

            DB::statement("ALTER TABLE exam_records MODIFY status ENUM('in_progress','submitted','graded') DEFAULT 'in_progress' COMMENT '状态'");
        }

        Schema::dropIfExists('exam_session_events');
        Schema::dropIfExists('exam_answer_drafts');
    }
};
