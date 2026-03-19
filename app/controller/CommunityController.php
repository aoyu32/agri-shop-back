<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\CommunityPost;
use app\model\PostLike;
use app\model\User;
use app\common\Response;
use think\facade\Db;

/**
 * 社区控制器
 */
class CommunityController extends BaseController
{
    /**
     * 获取帖子列表
     */
    public function list()
    {
        try {
            $category = $this->request->param('category', '');
            $keyword = $this->request->param('keyword', '');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $userId = $this->request->userId ?? 0;

            $query = CommunityPost::with(['user'])
                ->where('status', 1)
                ->order('is_top', 'desc')
                ->order('created_at', 'desc');

            // 分类筛选
            if ($category && $category !== 'all') {
                $query->where('category', $category);
            }

            // 关键词搜索
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereOr('title', 'like', "%{$keyword}%")
                        ->whereOr('content', 'like', "%{$keyword}%");
                });
            }

            $list = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
            ]);

            // 处理数据
            $data = [];
            foreach ($list as $post) {
                // 确保 images 和 tags 是数组
                $images = $post->images;
                if (is_string($images)) {
                    $images = json_decode($images, true) ?: [];
                }

                $tags = $post->tags;
                if (is_string($tags)) {
                    $tags = json_decode($tags, true) ?: [];
                }

                $item = [
                    'id' => $post->id,
                    'title' => $post->title,
                    'summary' => $post->summary,
                    'content' => mb_substr(strip_tags($post->content), 0, 200),
                    'images' => $images ?: [],
                    'category' => $post->category,
                    'tags' => $tags ?: [],
                    'view_count' => $post->view_count,
                    'like_count' => $post->like_count,
                    'comment_count' => $post->comment_count,
                    'is_top' => $post->is_top,
                    'is_essence' => $post->is_essence,
                    'created_at' => $post->created_at,
                    'author' => [
                        'id' => $post->user->id,
                        'username' => $post->user->username,
                        'nickname' => $post->user->nickname ?? $post->user->username,
                        'avatar' => $post->user->avatar ?? '',
                    ],
                ];

                // 检查当前用户是否点赞
                if ($userId) {
                    $item['is_liked'] = PostLike::where('post_id', $post->id)
                        ->where('user_id', $userId)
                        ->count() > 0;
                } else {
                    $item['is_liked'] = false;
                }

                $data[] = $item;
            }

            return Response::success([
                'list' => $data,
                'total' => $list->total(),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取帖子列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取热门帖子
     */
    public function hotPosts()
    {
        try {
            $limit = (int)$this->request->param('limit', 10);

            $posts = CommunityPost::with(['user'])
                ->where('status', 1)
                ->order('view_count', 'desc')
                ->order('like_count', 'desc')
                ->limit($limit * 2) // 多取一些，然后在PHP中排序
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
            return Response::error('获取热门帖子失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户统计信息
     */
    public function userStats()
    {
        try {
            $userId = $this->request->userId;

            // 帖子数
            $postCount = CommunityPost::where('user_id', $userId)
                ->where('status', 1)
                ->count();

            // 获赞数
            $likeCount = CommunityPost::where('user_id', $userId)
                ->where('status', 1)
                ->sum('like_count');

            // 评论数
            $commentCount = CommunityPost::where('user_id', $userId)
                ->where('status', 1)
                ->sum('comment_count');

            // 浏览数
            $viewCount = CommunityPost::where('user_id', $userId)
                ->where('status', 1)
                ->sum('view_count');

            return Response::success([
                'post_count' => $postCount,
                'like_count' => $likeCount,
                'comment_count' => $commentCount,
                'view_count' => $viewCount,
            ]);
        } catch (\Exception $e) {
            return Response::error('获取用户统计失败：' . $e->getMessage());
        }
    }

    /**
     * 创建帖子
     */
    public function create()
    {
        try {
            $userId = $this->request->userId;
            $title = $this->request->param('title');
            $summary = $this->request->param('summary');
            $content = $this->request->param('content');
            $images = $this->request->param('images', []);
            $category = $this->request->param('category');
            $tags = $this->request->param('tags', []);

            // 验证
            if (empty($title) || mb_strlen($title) < 5 || mb_strlen($title) > 100) {
                return Response::error('标题长度为5-100个字符');
            }

            if (empty($summary) || mb_strlen($summary) < 10 || mb_strlen($summary) > 300) {
                return Response::error('摘要长度为10-300个字符');
            }

            if (empty($content) || mb_strlen($content) < 20) {
                return Response::error('内容至少20个字符');
            }

            if (empty($category)) {
                return Response::error('请选择帖子分类');
            }

            // 创建帖子
            $post = new CommunityPost();
            $post->user_id = $userId;
            $post->title = $title;
            $post->summary = $summary;
            $post->content = $content;
            $post->images = $images;
            $post->category = $category;
            $post->tags = $tags;
            $post->status = 1; // 直接发布，不需要审核
            $post->save();

            return Response::success([
                'id' => $post->id,
            ], '发布成功');
        } catch (\Exception $e) {
            return Response::error('发布失败：' . $e->getMessage());
        }
    }

    /**
     * 点赞/取消点赞帖子
     */
    public function toggleLike()
    {
        try {
            $userId = $this->request->userId;
            $postId = $this->request->param('post_id');

            if (empty($postId)) {
                return Response::error('帖子ID不能为空');
            }

            $post = CommunityPost::find($postId);
            if (!$post) {
                return Response::error('帖子不存在');
            }

            // 检查是否已点赞
            $like = PostLike::where('post_id', $postId)
                ->where('user_id', $userId)
                ->find();

            Db::startTrans();
            try {
                if ($like) {
                    // 取消点赞
                    $like->delete();
                    $post->like_count = max(0, $post->like_count - 1);
                    $post->save();
                    $isLiked = false;
                    $message = '取消点赞';
                } else {
                    // 点赞
                    $newLike = new PostLike();
                    $newLike->post_id = $postId;
                    $newLike->user_id = $userId;
                    $newLike->save();

                    $post->like_count += 1;
                    $post->save();
                    $isLiked = true;
                    $message = '点赞成功';
                }

                Db::commit();

                return Response::success([
                    'is_liked' => $isLiked,
                    'like_count' => $post->like_count,
                ], $message);
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }
}
