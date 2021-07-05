<?php


namespace App\Services;

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
    public function getSharePower($user) {
        $children_collection = $user->children()->with('user')->get();

        $total_power = BigDecimal::zero();

        // 获取直邀的有效用户
        $direct_num = $this->getDirectChildrenNum($children_collection);

        if ($direct_num > 0 && $direct_num <= 10) {

            $trees = $this->getTrees($children_collection, $user->id, true);

            // 获取奖励的代数和比例
            $levels = $this->getShareRate($direct_num);

            foreach ($trees as $tree) {

                $new_tree = array_slice($tree, 0, $direct_num - 1);

                foreach($new_tree as $k=>$v) {
                    $rate = $levels[$k];
                    // 烧伤
                    if (BigDecimal::of($user->mine_power)->isGreaterThan($v->user->mine_power)) {
                        $power = BigDecimal::of($user->mine_power);
                    } else {
                        $power = BigDecimal::of($v->user->mine_power);
                    }

                    $power_add = $power->multipliedBy($rate);
                    $total_power = $total_power->plus($power_add);
                }
            }
        }

        return $total_power;
    }

    /**
     * 获取用户的团队算力
     *
     * @param $user
     */
    public function getTeamPower($user) {

        $collection = $user->children()->with('user')->get();

        // 获取该用户下的所有几条线
        $trees = $this->getTrees($collection, $user->id, true);

        $total_power = BigDecimal::zero();

        foreach ($trees as $tree) {
            $max_level = 0;
            $power = BigDecimal::zero();

            foreach ($tree as $k=>$v) {
                if ($v->user->vip_level > $max_level) {
                    $max_level = $v->user->vip_level;
                }

                $power = $power->plus($v->user->mine_power);
            }

            // 平级
            if ($max_level >= $user->vip_level) {
                $rate = 0.01;
            } else {
                $rate = $this->getTeamLevelRate($user->level);
            }

            // 计算总算李
            $total_power = $total_power->plus($power->multipliedBy($rate));
        }

        return $total_power;

    }

    /**
     * 更新用户的团队等级比例
     *
     * @param $user
     */
    public function updateParentTeamLevel($user) {
        // 获取用户的父级
        $parents = $user->parents()
            ->with('user', 'user.children')
            ->orderBy('level', 'asc')
            ->get();

        $min_level = 0;

        // TODO 推算平级
        foreach ($parents as $parent) {

            $new_level = $this->getUserTeamLevel($user, $parent->user->vip_level);

            if ($new_level > $parent->user->vip_level) {
                $parent->user->vip_level = $new_level;
            }

            if ($parent->user->vip_level == 0) {
                // 获取团队下所有的有效用户
                $count = $parent->user->children()
                    ->with('user')
                    ->sum(function($item){
                        if ($item->user->is_valid == 1) {
                            return 1;
                        }
                        return 0;
                    });

                if ($count >= 299) {
                    $parent->user->vip_level = 1;
                    $min_level = 1;
                }
            }
        }
    }

    protected function getUserTeamLevel($user, $level) {
        $collection  = $user->children()
            ->with('user')
            ->get();

        if ($level == 0) {
            // 获取团队下所有的有效用户
            $count = $collection
                ->sum(function($item){
                    if ($item->user->is_valid == 1) {
                        return 1;
                    }
                    return 0;
                });

            if ($count >= 300) {
                return 1;
            } else {
                return 0 ;
            }

        } else {
            // 获取该用户下的所有几条线
            $trees = $this->getTrees($collection, $user->id, true);

            $count = 0;

            foreach ($trees as $tree) {
                foreach ($tree as $k=>$v) {
                    if ($v->user->vip_level == $level) {
                        $count ++;
                        continue;
                    }
                }
            }

            if ($count >=3 ) {
                return $level + 1;
            } else {
                return $level;
            }
        }
    }

    /**
     * 获取直邀的有效用户
     *
     * @param $collection
     * @return mixed
     */
    public function getDirectChildrenNum($collection) {
        $direct_num = $collection
            ->where('level', '=', 1)
            ->sum(function ($item){
                if ($item->user->is_valid) {
                    return 1;
                }

                return 0;
            });

        return $direct_num;
    }

    /**
     * 获取分享比例
     *
     * @param $num
     * @return array|float[]
     */
    protected function getShareRate($num) {
        $rate = [0.3, 0.2, 0.15, 0.1, 0.5, 0.3, 0.3, 0.3, 0.3, 0.3];

        if ($num > 10) {
            return $rate;
        }

        if ($num == 0) {
            return [];
        }

        return array_slice($rate, 0, $num-1);
    }

    /**
     * 获取团队比例
     *
     * @param $level
     * @return float|int
     */
    protected function getTeamLevelRate($level) {
        $rate = [0.06, 0.1, 0.14, 0.18, 0.22];

        if ($level == 0) {
            return 0;
        }

        return $rate[$level];
    }


    /**
     * 获取树（从上而下）
     *
     * @param $collection
     * @param $root_id
     * @param bool $is_valid
     * @return array
     */
    protected function getTrees($collection,$root_id, $is_valid = false) {
        $trees = [];

        foreach ($collection as $k=>$v) {
            if (!$this->isHasChildren($collection, $v->user_id)) {
                if ($is_valid) {
                    if ($v->user->is_valid) {
                        $tree[0] = $v;
                    }
                }

                $parents = $this->getParents($collection,$v->parent_id,$root_id, $is_valid);
                if ($parents) {
                    $tree = array_merge($tree, $parents);
                }

                $trees[] = array_reverse($tree);
            }
        }

        return $trees;
    }

    /**
     * 获取父级树（从下而上）
     *
     * @param $collection
     * @param $root_id
     * @param bool $is_valid
     * @return array
     */
    public function getParentTree($user) {
        $children_collection = $user->children()->with('user')->get();

        $tree = $this->getParents2($children_collection, $user->id, true);

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

        while ($parent && $parent->parent_id &&  $parent->parent_id != $root_id) {
            if ($is_valid) {
                if ($parent->user->is_valid) {
                    $parents[] = $parent;
                }
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

        while ($parent) {
            if ($is_valid) {
                if ($parent->user->is_valid) {
                    $parents[] = $parent;
                }
            }
            $parent = $this->getParent($collection, $parent->parent_id);
        }

        return $parents;
    }

    protected function getParent($collection, $parent_id) {
        $filtered = $collection->where('child_id', '=', $parent_id)->first();

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
    public function getTeamNum($user) {
        $collection = $user->children()->with('user')->get();

        $num = $collection
            ->sum(function ($item){
                if ($item->user->is_valid == 1) {
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
    public function getTeamNewLevel($user) {
        if ($user->vip_level == 5) {
            return false;
        }

        $collection = $user->children()->with('user')->get();

        $trees = $this->getTrees($collection, $user->id, true);

        $count = 0;

        foreach ($trees as $tree) {
            foreach ($tree as $k=>$v) {
                if ($v->user->vip_level >= $user->vip_level) {
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

}