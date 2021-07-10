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
        $total_power = BigDecimal::zero();

        if ($user->is_valid == 0) {
            return $total_power;
        }

        $children_collection = $user->children()->with('child')->get();


        // 获取直邀的有效用户
        $direct_num = $this->getDirectChildrenNum($children_collection);

        if ($direct_num > 0 && $direct_num <= 10) {

            $trees = $this->getTrees($children_collection, $user->id, true);

            // 获取奖励的代数和比例
            $levels = $this->getShareRate($direct_num);

            foreach ($trees as $tree) {

                // 根据推荐数量获取对应的层级
                $new_tree = array_slice($tree, 0, $direct_num);

                foreach($new_tree as $k=>$v) {
                    $rate = $levels[$k];
                    // 烧伤
                    if (BigDecimal::of($user->mine_power)->isLessThan($v->mine_power)) {
                        $power = BigDecimal::of($user->mine_power);
                    } else {
                        $power = BigDecimal::of($v->mine_power);
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
        $total_power = BigDecimal::zero();

        if ($user->vip_level == 0 || $user->is_valid == 0) {
            return $total_power;
        }

        $collection = $user->children()->with('child')->get();

        // 获取该用户下的所有几条线
        $trees = $this->getTrees($collection, $user->id, true);


        foreach ($trees as $tree) {
            $max_level = 0;
            $power = BigDecimal::zero();

            foreach ($tree as $k=>$v) {
                if ($v->vip_level > $max_level) {
                    $max_level = $v->vip_level;
                }

                $power = $power->plus($v->mine_power);
            }

            // 平级
            if ($max_level >= $user->vip_level) {
                $rate = 0.01;
            } else {
                $rate = $this->getTeamLevelRate($user->vip_level);
            }

            // 计算总算李
            $total_power = $total_power->plus($power->multipliedBy($rate));
        }

        return $total_power;

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
                if ($item->child->is_valid == 1) {
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

        return array_slice($rate, 0, $num);
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

        return $rate[$level-1];
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
            if (!$this->isHasChildren($collection, $v->child_id)) {
                $tree = [];

                if ($is_valid) {
                    if ($v->child->is_valid == 1) {
                        $tree[0] = $v->child;
                    }
                }

                if ($v->parent_id) {
                    $parents = $this->getParents($collection, $v->parent_id, $root_id, $is_valid);
                    if ($parents) {
                        $tree = array_merge($tree, $parents);
                    }
                }

                if ($tree) {
                    $trees[] = array_reverse($tree);
                }
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
        $children_collection = $user->children()->with('child')->get();

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
                    $parents[] = $parent;
                }
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
    public function getTeamNum($user) {
        $collection = $user->children()->with('child')->get();

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
    public function getTeamNewLevel($user) {
        if ($user->vip_level == 5) {
            return false;
        }

        $collection = $user->children()->with('child')->get();

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

    public function getTeamNodes($user) {
        if ($user->vip_level == 5) {
            return 0;
        }

        $collection = $user->children()->with('child')->get();

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

}
