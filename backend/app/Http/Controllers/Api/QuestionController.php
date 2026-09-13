<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = Question::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->keyword . '%');
        }

        $perPage = $request->input('per_page', 15);
        $questions = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'questions' => $questions,
        ]);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限创建题目'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:question_categories,id',
            'type' => 'required|in:single_choice,multiple_choice,true_false,fill_blank,essay',
            'title' => 'required|string',
            'options' => 'nullable|array',
            'answer' => 'required|string',
            'analysis' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:3',
            'score' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = Question::create([
            'category_id' => $request->category_id,
            'type' => $request->type,
            'title' => $request->title,
            'options' => $request->options,
            'answer' => $request->answer,
            'analysis' => $request->analysis,
            'difficulty' => $request->difficulty ?? 1,
            'score' => $request->score ?? 1,
            'created_by' => $request->user()->id,
            'status' => 1,
        ]);

        return response()->json([
            'message' => '创建成功',
            'question' => $question,
        ], 201);
    }

    public function show(Question $question)
    {
        return response()->json([
            'question' => $question->load('category'),
        ]);
    }

    public function update(Request $request, Question $question)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限更新题目'], 403);
        }

        if ($request->user()->role !== 'admin' && $question->created_by !== $request->user()->id) {
            return response()->json(['error' => '只能编辑自己创建的题目'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:question_categories,id',
            'type' => 'required|in:single_choice,multiple_choice,true_false,fill_blank,essay',
            'title' => 'required|string',
            'options' => 'nullable|array',
            'answer' => 'required|string',
            'analysis' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:3',
            'score' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question->update([
            'category_id' => $request->category_id,
            'type' => $request->type,
            'title' => $request->title,
            'options' => $request->options,
            'answer' => $request->answer,
            'analysis' => $request->analysis,
            'difficulty' => $request->difficulty,
            'score' => $request->score,
        ]);

        return response()->json([
            'message' => '更新成功',
            'question' => $question,
        ]);
    }

    public function destroy(Question $question)
    {
        $user = request()->user();

        if (!in_array($user->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限删除题目'], 403);
        }

        if ($user->role !== 'admin' && $question->created_by !== $user->id) {
            return response()->json(['error' => '只能删除自己创建的题目'], 403);
        }

        $question->delete();

        return response()->json([
            'message' => '删除成功',
        ]);
    }

    public function categories()
    {
        $categories = QuestionCategory::where('status', 1)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => '无权限创建分类'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|exists:question_categories,id',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = QuestionCategory::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id ?? 0,
            'sort_order' => $request->sort_order ?? 0,
            'status' => 1,
        ]);

        return response()->json([
            'message' => '创建成功',
            'category' => $category,
        ], 201);
    }
}
