<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\AuthGroup;
use app\model\AuthGroupAccess;
use app\model\Meetings;
use app\model\Order;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\ProjectHuodong;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;
use think\session\driver\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MeetingsController extends AuthController
{
    public function list()
    {
        $data = Meetings::select();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function showMeetings()
    {

        $req = request()->get();
        $data = [];
        if (!empty($req['id'])){
            $data = Meetings::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }



    public function delMeetings()
    {
        $req = request()->post();
        $this->validate($req, [
            'id' => 'require|number'
        ]);

        Meetings::where('id', $req['id'])->delete();

        return out();
    }

    public function changeMeetings()
    {
        $req = request()->post();

        Meetings::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function editMeetings()
    {
        $req = request()->post();

        if ($img_url = upload_file('cover_image', false)) {
            $req['cover_image'] = $img_url;
        }

        if (!empty($req['id'])) {
            Meetings::where('id', $req['id'])->update($req);
        } else {

            Meetings::insert([
                'cover_image' => $req['cover_image'] ?? 'default_cover_image.jpg',  // 设置默认封面图
                'title' => $req['title'] ?? '默认标题',  // 设置默认标题
                'who' => $req['who'] ?? '默认参与者',  // 设置默认参与者
                'type' => $req['type'] ?? 1,  // 设置默认类型，如果没有提供
                'meeting_type' => $req['meeting_type'] ?? 1,  // 设置默认类型，如果没有提供
                'link' => $req['link'] ?? 'http://default.link',  // 设置默认链接
                'start_time' => isset($req['start_time']) ? date('Y-m-d H:i:s', strtotime($req['start_time'])) : date('Y-m-d H:i:s'),  // 格式化时间并设置默认值
                'end_time' => isset($req['end_time']) ? date('Y-m-d H:i:s', strtotime($req['end_time'])) : date('Y-m-d H:i:s', strtotime('+1 hour')),  // 格式化结束时间并设置默认值
                'content' => $req['content'] ?? '默认内容',  // 设置默认内容
            ]);
        }
        return out();
    }
    public function import()
    {
        return $this->fetch();
    }


    public function importSubmit()
    {
        ini_set('memory_limit', '5G');
        ini_set('max_execution_time', 300);
        set_time_limit(0); // 不限制时间
        $file = upload_file3('file');
        $spreadsheet = IOFactory::load($file);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        $type = request()->param('huodong_id'); // 1：参会奖励，2：邀请奖励 3：参会奖励2
        if (!$type) return out(null, 10001, '请选择活动类型');

        $newArr = [];
        foreach ($sheetData as $key => $value) {
            if ($key == 0) continue; // 跳过表头

            if ($type == 1) {
                // 参会奖励
                $duration = intval($value[17]); // 直播观看时长（秒）
                if ($duration < 1800) continue;

                // 提取手机号
                $rawPhone = $value[1] ?: $value[4] ?: $value[3]; // 优先绑定手机号、真实姓名、昵称
                preg_match('/1[3-9]\d{9}/', $rawPhone, $matches);
                if (!$matches || !$matches[0]) continue;
                $phone = $matches[0];

                $user = Db::name('user')->where('phone', $phone)->find();
                if (!$user) continue;

                $newArr[] = [
                    'id' => $user['id'],
                    'phone' => $phone,
                    'amount' => 5,
                    'remark' => '参会奖励：直播观看时长 ' . $duration . ' 秒',
                    'head' => 0,
                ];
            }

            if ($type == 2) {
                // 邀请奖励
                $phone = $value[2]; // 昵称列为手机号
                preg_match('/1[3-9]\d{9}/', $phone, $matches);
                if (!$matches || !$matches[0]) continue;
                $phone = $matches[0];

                $user = Db::name('user')->where('phone', $phone)->find();
                if (!$user) continue;

                $inviteCount = intval($value[3]);
                if ($inviteCount <= 0) continue;

                $newArr[] = [
                    'id' => $user['id'],
                    'phone' => $phone,
                    'amount' => $inviteCount * 2,
                    'remark' => '邀请奖励：邀请人数 ' . $inviteCount,
                    'head' => 0,
                ];
            }
            //3：参会奖励第二种方式  上传的 Excel 文件信息保存到 mp_uploaded_excel 表中
            if ($type == 3) {
                // 保存上传文件到根目录 /export 目录下
                $savePath = root_path() . 'export/';
                if (!is_dir($savePath)) {
                    mkdir($savePath, 0755, true);
                }

                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $filename = 'export_' . date('Ymd_His') . '.' . $ext;
                $finalPath = $savePath . $filename;

                // 拷贝文件
                copy($file, $finalPath);

                // 获取文件大小
                $size = filesize($finalPath);

                // 获取管理员 ID（如无管理员上下文请替换为实际 ID）
                $adminId = $this->adminUser['id'] ?? 0;

                // 写入数据库
                Db::name('uploaded_excel')->insert([
                    'file_name' => $filename,
                    'file_path' => $finalPath,
                    'file_size' => $size,
                    'file_ext' => $ext,
                    'upload_admin_id' => $adminId,
                    'remark' => '参会列表累计超过30分钟发放奖励',
                    'status' => 0, // 初始为待处理
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                return out([
                    'msg' => '文件信息已保存到数据库',
                    'filename' => $filename,
                    'path' => $finalPath
                ]);
            }
//            if ($type == 3) {
//                $durationMap = [];
//
//                foreach ($sheetData as $key => $value) {
//                    if ($key == 0) continue;
//
//                    // 昵称列，只保留手机号格式
//                    $raw = $value[1]; // 昵称列
//                    preg_match('/1[3-9]\d{9}/', $raw, $matches);
//                    if (!$matches || !$matches[0]) continue;
//
//                    $phone = $matches[0];
//
//                    // 转换时长 "00:01:36" → 秒数
//                    $timeText = $value[4] ?? ''; // 第5列是“时长”
//                    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeText)) continue;
//                    list($h, $m, $s) = array_map('intval', explode(':', $timeText));
//                    $seconds = $h * 3600 + $m * 60 + $s;
//
//                    // 累加
//                    if (!isset($durationMap[$phone])) {
//                        $durationMap[$phone] = 0;
//                    }
//                    $durationMap[$phone] += $seconds;
//                }
//
//                // 汇总后查库
//                foreach ($durationMap as $phone => $totalSeconds) {
//                    if ($totalSeconds < 1800) continue;
//
//                    $user = Db::name('user')->where('phone', $phone)->find();
//                    if (!$user) continue;
//
//                    $newArr[] = [
//                        'id' => $user['id'],
//                        'phone' => $phone,
//                        'amount' => 5,
//                        'remark' => '参会奖励：累计观看时长 ' . $totalSeconds . ' 秒',
//                        'head' => 0,
//                    ];
//                }
//            }

        }

        return out([
            'list' => $newArr,
            'count' => count($newArr),
            'admin' => $this->adminUser['nickname'] ?? '未知'
        ]);

    }

    public function importExec()
    {
        $req = request()->param();
        $ids = $req['ids'];
        $huodongId = $req['huodong_id'] ?? 0; // 获取当前活动类型
        $adminUser = $this->adminUser;

        Db::startTrans();
        try {
            foreach ($ids as $value) {
                $user = Db::name('user')->where('id', $value['id'])->lock(true)->find();
                if (!$user) continue;
                // 设置类型与备注
                $remark = '';
                $type = 0;
                if ($huodongId == 1) {
                    $remark = '线上会议参会奖励';
                    $type = 53; // 可自定义类型编号
                } elseif ($huodongId == 2) {
                    $remark = '线上会议邀请奖励';
                    $type = 54;
                }
                // 调用封装的方法进行余额变更
                User::changeInc($user['id'], $value['amount'], 'xuanchuan_balance', $type, 0, 1, $remark , $adminUser['id']);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

}
