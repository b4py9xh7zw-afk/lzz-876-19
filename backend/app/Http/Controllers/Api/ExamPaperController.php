<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamPaperController extends Controller
{
    public function index(Request $request)
    {
        $query = ExamPaper::with('creator');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->keyword . '%');
        }

        $perPage = $request->input('per_page', 15);
        $examPapers = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'exam_papers' => $examPapers,
        ]);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限创建试卷'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'total_time' => 'nullable|integer|min:1',
            'type' => 'nullable|in:fixed,random',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examPaper = ExamPaper::create([
            'title' => $request->title,
            'description' => $request->description,
            'total_score' => 0,
            'total_time' => $request->total_time ?? 60,
            'question_count' => 0,
            'type' => $request->type ?? 'fixed',
            'created_by' => $request->user()->id,
            'status' => 1,
        ]);

        return response()->json([
            'message' => '创建成功',
            'exam_paper' => $examPaper,
        ], 201);
    }

    public function show(ExamPaper $examPaper)
    {
        $examPaper->load(['questions', 'creator']);

        return response()->json([
            'exam_paper' => $examPaper,
        ]);
    }

    public function update(Request $request, ExamPaper $examPaper)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限更新试卷'], 403);
        }

        if ($user->role !== 'admin' && $examPaper->created_by !== $user->id) {
            return response()->json(['error' => '只能编辑自己创建的试卷'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'total_time' => 'nullable|integer|min:1',
            'type' => 'nullable|in:fixed,random',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examPaper->update([
            'title' => $request->title,
            'description' => $request->description,
            'total_time' => $request->total_time,
            'type' => $request->type,
            'status' => $request->status ?? $examPaper->status,
        ]);

        return response()->json([
            'message' => '更新成功',
            'exam_paper' => $examPaper,
        ]);
    }

    public function destroy(ExamPaper $examPaper)
    {
        $user = request()->user();

        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限删除试卷'], 403);
        }

        if ($user->role !== 'admin' && $examPaper->created_by !== $user->id) {
            return response()->json(['error' => '只能删除自己创建的试卷'], 403);
        }

        $examPaper->questions()->detach();
        $examPaper->delete();

        return response()->json([
            'message' => '删除成功',
        ]);
    }

    public function addQuestions(Request $request, ExamPaper $examPaper)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限添加题目'], 403);
        }

        if ($user->role !== 'admin' && $examPaper->created_by !== $user->id) {
            return response()->json(['error' => '只能操作自己创建的试卷'], 403);
        }

        $validator = Validator::make($request->all(), [
            'question_ids' => 'required|array',
            'question_ids.*' => 'exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existingIds = $examPaper->questions->pluck('id')->toArray();
        $newIds = array_diff($request->question_ids, $existingIds);

        $sortOrder = $examPaper->questions()->max('exam_paper_questions.sort_order') ?? 0;

        foreach ($newIds as $questionId) {
            $question = \App\Models\Question::find($questionId);
            $examPaper->questions()->attach($questionId, [
                'sort_order' => ++$sortOrder,
                'score' => $question->score,
            ]);
        }

        $examPaper->updateQuestionCountAndScore();

        return response()->json([
            'message' => '添加成功',
            'exam_paper' => $examPaper->fresh()->load('questions'),
        ]);
    }

    public function removeQuestion(ExamPaper $examPaper, $questionId)
    {
        $user = request()->user();

        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限移除题目'], 403);
        }

        if ($user->role !== 'admin' && $examPaper->created_by !== $user->id) {
            return response()->json(['error' => '只能操作自己创建的试卷'], 403);
        }

        $examPaper->questions()->detach($questionId);
        $examPaper->updateQuestionCountAndScore();

        return response()->json([
            'message' => '移除成功',
            'exam_paper' => $examPaper->fresh()->load('questions'),
        ]);
    }
}
