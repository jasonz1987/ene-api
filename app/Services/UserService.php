<?php


namespace App\Services;

use App\Model\InvitationLog;
use Brick\Math\BigDecimal;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Di\Annotation\Inject;

class UserService
{
    /**
     * @Inject()
     * @var ContainerInterface
     */
    private $container;

    /**
     * 获取用户的分享算力
     *
     * @param $user
     */
    public function getSmallPerformance($user, $collection = null, $is_sub = false) {
        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        // 获取直邀用户
        $children = $collection->where('level', '=', 1)->all();

        $big_performance = 0;
        $small_performance = 0;

        $big_user_id = 0;

        foreach($children as $child) {
            $user_performance = BigDecimal::of($child->child->team_performance)->plus($child->child->total_equipment_power);

            if ($user_performance->isGreaterThan($big_performance)) {
                $big_performance = $user_performance;
                $big_user_id = $child->child->id;
            }
        }

        $small_performance = BigDecimal::of($user->team_performance)->minus($big_performance);

        if ($is_sub && $big_user_id > 0) {
            $children_small_performance = BigDecimal::zero();

            foreach($children as $child) {
               if ($child->child_id == $big_user_id ) {
                   continue;
               }

                $user_performance = BigDecimal::of($child->child->team_performance)->plus($child->child->total_equipment_power);

                $children_small_performance = $children_small_performance->plus($user_performance->multipliedBy($this->getTeamLevelRate($child->child->team_level)));
            }

            $parent_small_performance = $small_performance->multipliedBy($this->getTeamLevelRate($user->team_level));

            if ($children_small_performance->isGreaterThan($parent_small_performance)) {
                $small_performance = 0;
            } else {
                $small_performance = $parent_small_performance->minus($children_small_performance);
            }
        }


        return $small_performance;
    }

    public function getTeamLevelRate($level) {

        $levels = [
            1   => 0.06,
            2   => 0.12,
            3   => 0.18,
            4   => 0.24,
            5   => 0.30
        ];

        return isset($levels[$level]) ? $levels[$level] : 0;
    }

    public function getSharePower($user, $collection = null) {
        $total_power = BigDecimal::zero();

        if (!$collection) {
            $collection = $user->children()->with('child')->orderBy('level', 'asc')->get();
        }

        $children = $collection->where('level', '<=', 2)->all();

        foreach ($children as $child) {
            if (BigDecimal::of($child->child->total_equipment_power)->isGreaterThan($user->total_equipment_power)) {
                $power = $user->total_equipment_power;
            } else {
                $power = $child->child->total_equipment_power;
            }

            $total_power = $total_power->plus(BigDecimal::of($power)->multipliedBy($this->getShareRate($child->level)));
        }

        return $total_power;
    }


    /**
     * 获取用户的团队算力
     *
     * @param $user
     */
    public function getTeamInfo($user, $collection = null) {
        // 获取小区业绩
        $small_performance = $this->getSmallPerformance($user, $collection, false);
        $team_level = $this->getTeamLevelByPerformance($small_performance);
        $team_power = $this->getSmallPerformance($user, $collection, true);

        return compact('team_power', 'small_performance', 'team_level');
    }

    public function getTeamLevelByPerformance($performance) {
        $levels = [5000, 20000, 60000, 150000, 300000];

        for($i = count($levels) - 1 ;$i >=0;$i--) {
            if (BigDecimal::of($performance)->isGreaterThanOrEqualTo($levels[$i])) {
                return $i+1;
            }
        }

        return 0;
    }


    /**
     * 获取分享比例
     *
     * @param $level
     * @return array|float[]
     */
    public function getShareRate($level) {
        $levels = [
            1 => 0.3,
            2 => 0.2
        ];

        return isset($levels[$level]) ? $levels[$level] : 0;
    }


    /**
     * 获取父级树（从下而上）
     *
     * @param $collection
     * @param $root_id
     * @param bool $is_valid
     * @return array
     */
    public function getParentTree($user, $collection = null) {
        if (!$collection) {
            $collection = $user->parents()->with('user')->get();
        }

        $tree = $this->getParents2($collection, $user->id, true);

        return $tree;
    }

    /**
     * 获取父节点
     *
     * @param $collection
     * @param $user_id
     * @param $root_id
     * @param bool $is_valid
     * @return mixed
     */
    protected function getParents($collection, $user_id, $root_id, $is_valid = false) {
        $parent = $this->getParent($collection, $user_id);

        $parents = [];

        while ($parent && $parent->parent_id) {
            if ($is_valid) {
                if ($parent->child->is_valid == 1) {
                    $parents[] = $parent->child;
                }
            } else {
                $parents[] = $parent->child;
            }

            if ($parent->parent_id == $root_id) {
                break;
            }

            $parent = $this->getParent($collection, $parent->parent_id);
        }

        return $parents;
    }

    /**
     * 获取父节点
     *
     * @param $collection
     * @param $user_id
     * @param $root_id
     * @param bool $is_valid
     * @return mixed
     */
    protected function getParents2($collection, $user_id, $is_valid = false) {
        $parent = $this->getParent($collection, $user_id);

        $parents = [];

        while ($parent) {
            if ($is_valid) {
                if ($parent->child->is_valid == 1) {
                    $parents[] = $parent->child;
                }
            } else {
                $parents[] = $parent->child;
            }
            $parent = $this->getParent($collection, $parent->parent_id);
        }

        return $parents;
    }

    protected function getParent($collection, $user_id) {
        $filtered = $collection->where('child_id', '=', $user_id)
            ->first();

        return $filtered;
    }

    protected function isHasChildren($collection, $user_id) {
        $filtered = $collection->where('parent_id', '=', $user_id)->first();

        return $filtered ? true : false;
    }

    /**
     * 获取团队有效用户
     *
     * @param $user
     * @return mixed
     */
    public function getTeamNum($user, $collection = null) {
        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $num = $collection
            ->sum(function ($item){
                if ($item->child->is_valid == 1) {
                    return 1;
                }

                return 0;
            });

        return $num;
    }

    /**
     * 获取团队有效部门
     *
     * @param $user
     * @param $level
     * @return mixed
     */
    public function getTeamNewLevel($user, $collection = null) {
        if ($user->vip_level == 5) {
            return false;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $trees = $this->getTrees($collection, $user->id, true);

        $count = 0;

        foreach ($trees as $tree) {
            foreach ($tree as $k=>$v) {
                if ($v->vip_level >= $user->vip_level) {
                    $count++;
                    break;
                }
            }
        }

        if($count >= 3) {
            return true;
        }

        return false;
    }

    public function getTeamNodes2($user, $collection = null) {
        if ($user->vip_level == 5) {
            return 0;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $trees = $this->getTrees($collection, $user->id, true);

        $count = 0;

        foreach ($trees as $tree) {
            foreach ($tree as $k=>$v) {
                if ($v->vip_level >= $user->vip_level) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    public function getTeamNodes($user, $collection = null) {
        if ($user->vip_level == 0) {
            return 0;
        }

        if (!$collection) {
            $collection = $user->children()->with('child')->get();
        }

        $children = $collection->where('level', '=', 1)->all();

        $count = 0;
        $uids = [];

        foreach ($children as $child) {
            if ($child->child->vip_level == $user->vip_level) {
                $count ++;
                continue;
            }

            $uids[] = $child->child_id;
        }

        if ($uids) {
            $trees = InvitationLog::join('users', 'users.id','=', 'invitation_logs.child_id')
                ->selectRaw('count(1) as count, user_id')
                ->whereIn('user_id', $uids)
                ->where('vip_level', '=', $user->vip_level)
                ->groupBy('user_id')
                ->get();


            foreach ($trees as $tree) {
                if ($tree->count > 0) {
                    $count ++;
                }
            }
        }

        return $count;
    }


}
