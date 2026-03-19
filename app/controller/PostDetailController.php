<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\CommunityPost;
use app\model\PostLike;
use app\common\Response;

/**
 * 帖子详情控制器
 */
class PostDetailController extends BaseController
{
    /**
     * 获取帖子详情
     */
    public function detail()
    {
        try {
            $postId = $this->request->param('id');
            $userId = $this->request->userId ?? 0;

            if (empty($postId)) {
                return Response::error('帖子ID不能为空');
            }

            $post = CommunityPost::with(['user'])->find($postId);

            if (!$post || $post->status != 1) {
                return Response::error('帖子不存在或已被删除');
            }

            // 增加浏览量
            $post->view_count += 1;
            $post->save();

            // 检查当前用户是否点赞
            $isLiked = false;
            if ($userId) {
                $isLiked = PostLike::where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->count() > 0;
            }

            // 确保 images 和 tags 是数组
            $images = $post->images;
            if (is_string($images)) {
                $images = json_decode($images, true) ?: [];
            }

            $tags = $post->tags;
            if (is_string($tags)) {
                $tags = json_decode($tags, true) ?: [];
            }

            $data = [
                'id' => $post->id,
                'title' => $post->title,
                'summary' => $post->summary,
                'content' => $post->content,
                'images' => $images ?: [],
                'category' => $post->category,
                'tags' => $tags ?: [],
                'view_count' => $post->view_count,
                'like_count' => $post->like_count,
                'comment_count' => $post->comment_count,
                'is_top' => $post->is_top,
                'is_essence' => $post->is_essence,
                'is_liked' => $isLiked,
                'created_at' => $post->created_at,
                'author' => [
                    'id' => $post->user->id,
                    'username' => $post->user->username,
                    'nickname' => $post->user->nickname ?? $post->user->username,
                    'avatar' => $post->user->avatar ?? '',
                ],
            ];

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error('获取帖子详情失败：' . $e->getMessage());
        }
    }

    /**
     * 获取相关推荐帖子
     */
    public function relatedPosts()
    {
        try {
            $postId = $this->request->param('id');
            $limit = (int)$this->request->param('limit', 5);

            if (empty($postId)) {
                return Response::error('帖子ID不能为空');
            }

            $currentPost = CommunityPost::find($postId);
            if (!$currentPost) {
                return Response::error('帖子不存在');
            }

            // 获取相同分类的其他帖子
            $posts = CommunityPost::with(['user'])
                ->where('id', '<>', $postId)
                ->where('status', 1)
                ->where('category', $currentPost->category)
                ->order('view_count', 'desc')
                ->order('like_count', 'desc')
                ->limit($limit * 2)
                ->select()
                ->toArray();

            // 在 PHP 中计算热度并排序
            usort($posts, function ($a, $b) {
                $scoreA = $a['view_count'] + $a['like_count'] * 2;
                $scoreB = $b['view_count'] + $b['like_count'] * 2;
                return $scoreB - $scoreA;
            });

            // 截取指定数量
            $posts = array_slice($posts, 0, $limit);

            $data = [];
            foreach ($posts as $post) {
                $data[] = [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'view_count' => $post['view_count'],
                    'like_count' => $post['like_count'],
                    'comment_count' => $post['comment_count'],
                ];
            }

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error('获取相关推荐失败：' . $e->getMessage());
        }
    }
}
