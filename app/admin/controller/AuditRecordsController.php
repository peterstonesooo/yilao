<?php

namespace app\admin\controller;

use app\model\AuditRecords;



class AuditRecordsController extends AuthController
{
    public function auditList()
    {
        $req = request()->param();
        $builder = AuditRecords:: alias('a')->order(['a.created_at'=>'desc','a.updated_at'=>'desc']);


        // 修正 field 语法错误
        $builder->field('a.*, u.phone, u.ic_number, u.realname')
            ->join('mp_user u', 'a.user_id = u.id');


        if (!empty($req['start_date'])) {
            $builder->where('a.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('a.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (!empty($req['phone'])) {
            $builder->where('u.phone', $req['phone']);
        }

        if (!empty($req['audit_type'])) {
            $builder->where('a.audit_type', $req['audit_type']);
        }else{
            $builder->whereIn('a.audit_type', [1, 2]);
        }
        $count = $builder->count();
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('total_num', $count);


        return $this->fetch();
    }
}
