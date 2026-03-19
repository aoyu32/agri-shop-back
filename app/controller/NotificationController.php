<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Notification;
use app\common\Response;

/**
 * 消息通知控制器
 */
class NotificationController extends BaseController
{
    /**
     * 获取通知列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;
            $type = $this->request->param('type', ''); // all, order, review, reply, system
            $isRead = $this->request->param('is_read', ''); // '', 0, 1
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            // 构建查询
            $query = Notification::where('user_id', $userId)
                ->order('created_at', 'desc');

            // 类型筛选
            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }

            // 已读状态筛选
            if ($isRead !== '') {
                $query->where('is_read', (int)$isRead);
            }

            // 分页查询
            $notifications = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            // 格式化数据
            $list = [];
            foreach ($notifications->items() as $notification) {
                $list[] = [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'content' => $notification->content,
                    'related_id' => $notification->related_id,
                    'related_type' => $notification->related_type,
                    'is_read' => $notification->is_read,
                    'time' => $notification->created_at,
                    'created_at' => $notification->created_at
                ];
            }

            return Response::success([
                'list' => $list,
                'total' => $notifications->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取通知列表失败：' . $e->getMessage());
            return Response::error('获取通知列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取未读数量
     */
    public function unreadCount()
    {
        try {
            $userId = $this->request->userId;
            $count = Notification::getUnreadCount($userId);

            return Response::success([
                'count' => $count
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取未读数量失败：' . $e->getMessage());
            return Response::error('获取未读数量失败：' . $e->getMessage());
        }
    }

    /**
     * 标记单条通知为已读
     */
    public function markAsRead()
    {
        try {
            $userId = $this->request->userId;
            $notificationId = $this->request->param('id');

            if (!$notificationId) {
                return Response::validateError('通知ID不能为空');
            }

            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->find();

            if (!$notification) {
                return Response::error('通知不存在');
            }

            $notification->markAsRead();

            return Response::success([], '标记成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('标记已读失败：' . $e->getMessage());
            return Response::error('标记已读失败：' . $e->getMessage());
        }
    }

    /**
     * 标记所有通知为已读
     */
    public function markAllAsRead()
    {
        try {
            $userId = $this->request->userId;
            Notification::markAllAsRead($userId);

            return Response::success([], '全部标记为已读');
        } catch (\Exception $e) {
            \think\facade\Log::error('标记全部已读失败：' . $e->getMessage());
            return Response::error('标记全部已读失败：' . $e->getMessage());
        }
    }

    /**
     * 删除通知
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $notificationId = $this->request->param('id');

            if (!$notificationId) {
                return Response::validateError('通知ID不能为空');
            }

            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->find();

            if (!$notification) {
                return Response::error('通知不存在');
            }

            $notification->delete();

            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('删除通知失败：' . $e->getMessage());
            return Response::error('删除通知失败：' . $e->getMessage());
        }
    }

    /**
     * 清空已读通知
     */
    public function clearRead()
    {
        try {
            $userId = $this->request->userId;

            Notification::where('user_id', $userId)
                ->where('is_read', 1)
                ->delete();

            return Response::success([], '清空成功');
        } catch (\Exception $e) {
            \think\facade\Log::error('清空已读通知失败：' . $e->getMessage());
            return Response::error('清空已读通知失败：' . $e->getMessage());
        }
    }
}
