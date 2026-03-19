<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\CommunityPost;
use app\model\PostComment;
use app\model\CommentLike;
use app\common\Response;
use think\facade\Db;

/**
 * 帖子评论控制器
 */
class PostCommentController extends BaseController
{
    /**
     * 获取评论列表
     */
    public function list()
    {
        try {
            $postId = $this->request->param('post_id');
            $userId = $this->request->userId ?? 0;

            if (empty($postId)) {
                return Response::error('帖子ID不能为空');
            }

            // 获取一级评论
            $comments = PostComment::with(['user', 'replyToUser'])
                ->where('post_id', $postId)
                ->where('parent_id', null)
                ->where('status', 1)
                ->order('created_at', 'desc')
                ->select();

            $data = [];
            foreach ($comments as $comment) {
                $item = [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'like_count' => $comment->like_count,
                    'created_at' => $comment->created_at,
                    'author' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'nickname' => $comment->user->nickname ?? $comment->user->username,
                        'avatar' => $comment->user->avatar ?? '',
                    ],
                ];

                // 检查当前用户是否点赞
                if ($userId) {
                    $item['is_liked'] = CommentLike::where('comment_id', $comment->id)
                        ->where('user_id', $userId)
                        ->count() > 0;
                } else {
                    $item['is_liked'] = false;
                }

                // 获取回复列表
                $replies = PostComment::with(['user', 'replyToUser'])
                    ->where('parent_id', $comment->id)
                    ->where('status', 1)
                    ->order('created_at', 'asc')
                    ->select();

                $item['replies'] = [];
                foreach ($replies as $reply) {
                    $replyItem = [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'like_count' => $reply->like_count,
                        'created_at' => $reply->created_at,
                        'author' => [
                            'id' => $reply->user->id,
                            'username' => $reply->user->username,
                            'nickname' => $reply->user->nickname ?? $reply->user->username,
                            'avatar' => $reply->user->avatar ?? '',
                        ],
                    ];

                    // 如果是回复某人
                    if ($reply->reply_to_user_id && $reply->replyToUser) {
                        $replyItem['reply_to'] = [
                            'id' => $reply->replyToUser->id,
                            'username' => $reply->replyToUser->username,
                            'nickname' => $reply->replyToUser->nickname ?? $reply->replyToUser->username,
                        ];
                    }

                    // 检查当前用户是否点赞
                    if ($userId) {
                        $replyItem['is_liked'] = CommentLike::where('comment_id', $reply->id)
                            ->where('user_id', $userId)
                            ->count() > 0;
                    } else {
                        $replyItem['is_liked'] = false;
                    }

                    $item['replies'][] = $replyItem;
                }

                $data[] = $item;
            }

            return Response::success($data);
        } catch (\Exception $e) {
            return Response::error('获取评论列表失败：' . $e->getMessage());
        }
    }

    /**
     * 发表评论
     */
    public function create()
    {
        try {
            $userId = $this->request->userId;
            $postId = $this->request->param('post_id');
            $content = $this->request->param('content');
            $parentId = $this->request->param('parent_id', null);
            $replyToUserId = $this->request->param('reply_to_user_id', null);

            // 验证
            if (empty($postId)) {
                return Response::error('帖子ID不能为空');
            }

            if (empty($content) || mb_strlen($content) < 2) {
                return Response::error('评论内容至少2个字符');
            }

            if (mb_strlen($content) > 500) {
                return Response::error('评论内容不能超过500个字符');
            }

            // 检查帖子是否存在
            $post = CommunityPost::find($postId);
            if (!$post || $post->status != 1) {
                return Response::error('帖子不存在或已被删除');
            }

            // 如果是回复评论，检查父评论是否存在
            if ($parentId) {
                $parentComment = PostComment::find($parentId);
                if (!$parentComment || $parentComment->post_id != $postId) {
                    return Response::error('父评论不存在');
                }
            }

            Db::startTrans();
            try {
                // 创建评论
                $comment = new PostComment();
                $comment->post_id = $postId;
                $comment->user_id = $userId;
                $comment->parent_id = $parentId;
                $comment->reply_to_user_id = $replyToUserId;
                $comment->content = $content;
                $comment->status = 1;
                $comment->save();

                // 更新帖子评论数
                $post->comment_count += 1;
                $post->save();

                Db::commit();

                // 返回评论信息
                $comment = PostComment::with(['user'])->find($comment->id);

                return Response::success([
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'like_count' => $comment->like_count,
                    'created_at' => $comment->created_at,
                    'author' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'nickname' => $comment->user->nickname ?? $comment->user->username,
                        'avatar' => $comment->user->avatar ?? '',
                    ],
                    'is_liked' => false,
                ], '评论成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('评论失败：' . $e->getMessage());
        }
    }

    /**
     * 点赞/取消点赞评论
     */
    public function toggleLike()
    {
        try {
            $userId = $this->request->userId;
            $commentId = $this->request->param('comment_id');

            if (empty($commentId)) {
                return Response::error('评论ID不能为空');
            }

            $comment = PostComment::find($commentId);
            if (!$comment || $comment->status != 1) {
                return Response::error('评论不存在或已被删除');
            }

            // 检查是否已点赞
            $like = CommentLike::where('comment_id', $commentId)
                ->where('user_id', $userId)
                ->find();

            Db::startTrans();
            try {
                if ($like) {
                    // 取消点赞
                    $like->delete();
                    $comment->like_count = max(0, $comment->like_count - 1);
                    $comment->save();
                    $isLiked = false;
                    $message = '取消点赞';
                } else {
                    // 点赞
                    $newLike = new CommentLike();
                    $newLike->comment_id = $commentId;
                    $newLike->user_id = $userId;
                    $newLike->save();

                    $comment->like_count += 1;
                    $comment->save();
                    $isLiked = true;
                    $message = '点赞成功';
                }

                Db::commit();

                return Response::success([
                    'is_liked' => $isLiked,
                    'like_count' => $comment->like_count,
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
