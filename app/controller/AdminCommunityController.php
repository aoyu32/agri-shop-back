<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Response;
use app\model\CommentLike;
use app\model\CommunityPost;
use app\model\PostComment;
use app\model\PostLike;
use app\model\User;

/**
 * 管理后台社区管理控制器
 */
class AdminCommunityController extends BaseController
{
    /**
     * 帖子列表
     */
    public function list()
    {
        try {
            $keyword = trim((string) $this->request->param('keyword', ''));
            $category = trim((string) $this->request->param('category', ''));
            $status = $this->request->param('status', '');
            $page = (int) $this->request->param('page', 1);
            $pageSize = (int) $this->request->param('page_size', 10);

            $query = CommunityPost::with(['user'])->order('id', 'desc');

            if ($keyword !== '') {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->whereOr('title', 'like', "%{$keyword}%")
                        ->whereOr('summary', 'like', "%{$keyword}%")
                        ->whereOr('content', 'like', "%{$keyword}%");
                });
            }

            if ($category !== '') {
                $query->where('category', $category);
            }

            if ($status !== '' && in_array((int) $status, [0, 1, 2, 3], true)) {
                $query->where('status', (int) $status);
            }

            $posts = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);

            $list = [];
            foreach ($posts->items() as $post) {
                $list[] = $this->formatPost($post);
            }

            return Response::success([
                'list' => $list,
                'total' => $posts->total(),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取帖子列表失败：' . $e->getMessage());
        }
    }

    /**
     * 帖子详情
     */
    public function detail()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('帖子ID不能为空');
            }

            $post = CommunityPost::with(['user'])->find($id);
            if (!$post) {
                return Response::error('帖子不存在');
            }

            return Response::success([
                'post' => $this->formatPost($post, true),
            ]);
        } catch (\Exception $e) {
            return Response::error('获取帖子详情失败：' . $e->getMessage());
        }
    }

    /**
     * 新增帖子
     */
    public function create()
    {
        try {
            $userId = (int) $this->request->param('user_id');
            $title = trim((string) $this->request->param('title'));
            $summary = trim((string) $this->request->param('summary'));
            $content = (string) $this->request->param('content');
            $images = $this->request->param('images', []);
            $category = trim((string) $this->request->param('category'));
            $tags = $this->request->param('tags', []);
            $status = (int) $this->request->param('status', 1);
            $isTop = (int) $this->request->param('is_top', 0);
            $isEssence = (int) $this->request->param('is_essence', 0);

            $validationResult = $this->validatePostData($userId, $title, $summary, $content, $category);
            if ($validationResult !== true) {
                return $validationResult;
            }

            $post = CommunityPost::create([
                'user_id' => $userId,
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'images' => is_array($images) ? $images : [],
                'category' => $category,
                'tags' => is_array($tags) ? $tags : [],
                'status' => in_array($status, [0, 1, 2, 3], true) ? $status : 1,
                'is_top' => $isTop ? 1 : 0,
                'is_essence' => $isEssence ? 1 : 0,
            ]);

            return Response::success([
                'post' => ['id' => $post->id],
            ], '新增帖子成功');
        } catch (\Exception $e) {
            return Response::error('新增帖子失败：' . $e->getMessage());
        }
    }

    /**
     * 更新帖子
     */
    public function update()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('帖子ID不能为空');
            }

            $post = CommunityPost::find($id);
            if (!$post) {
                return Response::error('帖子不存在');
            }

            $userId = (int) $this->request->param('user_id', $post->user_id);
            $title = trim((string) $this->request->param('title', $post->title));
            $summary = trim((string) $this->request->param('summary', $post->summary));
            $content = (string) $this->request->param('content', $post->content);
            $images = $this->request->param('images', $post->images);
            $category = trim((string) $this->request->param('category', $post->category));
            $tags = $this->request->param('tags', $post->tags);
            $status = (int) $this->request->param('status', $post->status);
            $isTop = (int) $this->request->param('is_top', $post->is_top);
            $isEssence = (int) $this->request->param('is_essence', $post->is_essence);

            $validationResult = $this->validatePostData($userId, $title, $summary, $content, $category);
            if ($validationResult !== true) {
                return $validationResult;
            }

            $post->user_id = $userId;
            $post->title = $title;
            $post->summary = $summary;
            $post->content = $content;
            $post->images = is_array($images) ? $images : [];
            $post->category = $category;
            $post->tags = is_array($tags) ? $tags : [];
            $post->status = in_array($status, [0, 1, 2, 3], true) ? $status : 1;
            $post->is_top = $isTop ? 1 : 0;
            $post->is_essence = $isEssence ? 1 : 0;
            $post->save();

            return Response::success([], '更新帖子成功');
        } catch (\Exception $e) {
            return Response::error('更新帖子失败：' . $e->getMessage());
        }
    }

    /**
     * 上架/下架帖子
     */
    public function updateStatus()
    {
        try {
            $id = (int) $this->request->param('id');
            $status = (int) $this->request->param('status');

            if (!$id) {
                return Response::validateError('帖子ID不能为空');
            }
            if (!in_array($status, [1, 3], true)) {
                return Response::validateError('帖子状态不正确');
            }

            $post = CommunityPost::find($id);
            if (!$post) {
                return Response::error('帖子不存在');
            }

            $post->status = $status;
            $post->save();

            return Response::success([], $status === 1 ? '帖子已上架' : '帖子已下架');
        } catch (\Exception $e) {
            return Response::error('更新帖子状态失败：' . $e->getMessage());
        }
    }

    /**
     * 删除帖子
     */
    public function delete()
    {
        try {
            $id = (int) $this->request->param('id');
            if (!$id) {
                return Response::validateError('帖子ID不能为空');
            }

            $post = CommunityPost::find($id);
            if (!$post) {
                return Response::error('帖子不存在');
            }

            $post->status = 0;
            $post->save();

            return Response::success([], '删除帖子成功');
        } catch (\Exception $e) {
            return Response::error('删除帖子失败：' . $e->getMessage());
        }
    }

    /**
     * 校验帖子数据
     */
    private function validatePostData(int $userId, string $title, string $summary, string $content, string $category)
    {
        if (!$userId) {
            return Response::validateError('请选择发布用户');
        }
        if (!User::find($userId)) {
            return Response::validateError('发布用户不存在');
        }
        if ($title === '' || mb_strlen($title) < 5 || mb_strlen($title) > 100) {
            return Response::validateError('标题长度需为5-100个字符');
        }
        if ($summary === '' || mb_strlen($summary) < 10 || mb_strlen($summary) > 300) {
            return Response::validateError('摘要长度需为10-300个字符');
        }
        if ($content === '' || mb_strlen($content) < 20) {
            return Response::validateError('帖子内容至少20个字符');
        }
        if ($category === '') {
            return Response::validateError('请选择帖子分类');
        }

        return true;
    }

    /**
     * 格式化帖子输出
     */
    private function formatPost(CommunityPost $post, bool $withExtra = false): array
    {
        $images = is_array($post->images) ? $post->images : (json_decode((string) $post->images, true) ?: []);
        $tags = is_array($post->tags) ? $post->tags : (json_decode((string) $post->tags, true) ?: []);

        $data = [
            'id' => $post->id,
            'user_id' => $post->user_id,
            'title' => $post->title,
            'summary' => $post->summary,
            'content' => $post->content,
            'images' => $images,
            'tags' => $tags,
            'category' => $post->category,
            'view_count' => (int) $post->view_count,
            'like_count' => (int) $post->like_count,
            'comment_count' => (int) $post->comment_count,
            'status' => (int) $post->status,
            'status_text' => $this->getStatusText((int) $post->status),
            'is_top' => (int) $post->is_top,
            'is_essence' => (int) $post->is_essence,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'author' => $post->user ? [
                'id' => $post->user->id,
                'username' => $post->user->username,
                'nickname' => $post->user->nickname,
                'avatar' => $post->user->avatar,
            ] : null,
        ];

        if ($withExtra) {
            $commentIds = PostComment::where('post_id', $post->id)->column('id');
            $data['like_user_count'] = PostLike::where('post_id', $post->id)->count();
            $data['comment_rows'] = count($commentIds);
            $data['comment_like_count'] = empty($commentIds) ? 0 : CommentLike::whereIn('comment_id', $commentIds)->count();
        }

        return $data;
    }

    /**
     * 状态文本
     */
    private function getStatusText(int $status): string
    {
        $map = [
            0 => '已删除',
            1 => '已上架',
            2 => '审核中',
            3 => '已下架',
        ];

        return $map[$status] ?? '未知状态';
    }
}
