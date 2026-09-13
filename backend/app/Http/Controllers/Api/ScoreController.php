<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function statistics(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $totalUsers = User::where('status', 1)->count();
        $totalExams = ExamPaper::where('status', 1)->count();
        $totalRecords = ExamRecord::where('status', 'graded')->count();

        $avgScore = ExamRecord::where('status', 'graded')
            ->avg('score') ?? 0;

        $passCount = ExamRecord::where('status', 'graded')
            ->where('score', '>=', 60)
            ->count();
        $totalCount = ExamRecord::where('status', 'graded')->count();
        $passRate = $totalCount > 0 ? ($passCount / $totalCount) * 100 : 0;

        $recentRecords = ExamRecord::with(['user', 'examPaper'])
            ->where('status', 'graded')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'statistics' => [
                'total_users' => $totalUsers,
                'total_exams' => $totalExams,
                'total_records' => $totalRecords,
                'avg_score' => round($avgScore, 2),
                'pass_rate' => round($passRate, 2),
            ],
            'recent_records' => $recentRecords,
        ]);
    }

    public function ranking(Request $request, ExamPaper $examPaper)
    {
        $ranking = ExamRecord::with('user')
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'graded')
            ->orderBy('score', 'desc')
            ->limit($request->input('limit', 50))
            ->get()
            ->map(function ($record, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => [
                        'id' => $record->user->id,
                        'username' => $record->user->username,
                        'real_name' => $record->user->real_name,
                    ],
                    'score' => $record->score,
                    'submitted_at' => $record->updated_at,
                ];
            });

        return response()->json([
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
            ],
            'ranking' => $ranking,
        ]);
    }

    public function analysis(Request $request, ExamPaper $examPaper)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $totalAttempts = ExamRecord::where('exam_paper_id', $examPaper->id)
            ->where('status', 'graded')
            ->count();

        if ($totalAttempts === 0) {
            return response()->json([
                'message' => '暂无考试数据',
                'analysis' => null,
            ]);
        }

        $avgScore = ExamRecord::where('exam_paper_id', $examPaper->id)
            ->where('status', 'graded')
            ->avg('score');

        $highScore = ExamRecord::where('exam_paper_id', $examPaper->id)
            ->where('status', 'graded')
            ->max('score');

        $lowScore = ExamRecord::where('exam_paper_id', $examPaper->id)
            ->where('status', 'graded')
            ->min('score');

        $scoreDistribution = [];
        $ranges = [
            '90-100' => [90, 100],
            '80-89' => [80, 89],
            '70-79' => [70, 79],
            '60-69' => [60, 69],
            '0-59' => [0, 59],
        ];

        foreach ($ranges as $range => [$min, $max]) {
            $count = ExamRecord::where('exam_paper_id', $examPaper->id)
                ->where('status', 'graded')
                ->whereBetween('score', [$min, $max])
                ->count();
            $scoreDistribution[$range] = $count;
        }

        $questionStats = DB::table('exam_record_answers')
            ->join('exam_paper_questions', 'exam_record_answers.question_id', '=', 'exam_paper_questions.question_id')
            ->where('exam_paper_questions.exam_paper_id', $examPaper->id)
            ->select(
                'exam_record_answers.question_id',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(IF(is_correct = 1, 1, 0)) as correct_count')
            )
            ->groupBy('exam_record_answers.question_id')
            ->get()
            ->map(function ($stat) {
                return [
                    'question_id' => $stat->question_id,
                    'correct_rate' => $stat->total_attempts > 0
                        ? round($stat->correct_count / $stat->total_attempts * 100, 2)
                        : 0,
                ];
            });

        return response()->json([
            'analysis' => [
                'total_attempts' => $totalAttempts,
                'avg_score' => round($avgScore, 2),
                'high_score' => $highScore,
                'low_score' => $lowScore,
                'score_distribution' => $scoreDistribution,
                'question_stats' => $questionStats,
            ],
        ]);
    }
}
