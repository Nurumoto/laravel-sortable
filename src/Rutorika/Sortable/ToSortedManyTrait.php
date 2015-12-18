<?php

namespace Rutorika\Sortable;

/**
 * Class MorphToSortedManyTrait.
 *
 * @traitUses Illuminate\Database\Eloquent\Model
 */
trait ToSortedManyTrait
{
    protected $orderColumn;

    protected function setOrderColumn($orderColumn)
    {
        $this->orderColumn = $orderColumn;
        $this->withPivot($orderColumn);
        $this->orderBy($orderColumn, 'ASC');
    }

    /**
     * Attach a model to the parent.
     *
     * @param mixed $id
     * @param array $attributes
     * @param bool  $touch
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        $attributes[$this->getOrderColumnName()] = $this->getNextPosition();

        parent::attach($id, $attributes, $touch);
    }

    /**
     * Moves $entity before $positionEntity.
     *
     * @param Model $entity         What to move
     * @param Model $positionEntity Where to move
     */
    public function moveBefore($entity, $positionEntity)
    {
        $this->move('moveBefore', $entity, $positionEntity);
    }

    /**
     * Moves $entity after $positionEntity.
     *
     * @param Model $entity         What to move
     * @param Model $positionEntity Where to move
     */
    public function moveAfter($entity, $positionEntity)
    {
        $this->move('moveAfter', $entity, $positionEntity);
    }

    /**
     * @param string $action
     * @param Model  $entity
     * @param Model  $positionEntity
     */
    public function move($action, $entity, $positionEntity)
    {
        $positionColumn = $this->getOrderColumnName();

        $oldPosition = $entity->pivot->$positionColumn;
        $newPosition = $positionEntity->pivot->$positionColumn;

        $isMoveBefore = $action === 'moveBefore'; // otherwise moveAfter

        if ($oldPosition > $newPosition) {
            $this->queryBetween($newPosition, $oldPosition, $isMoveBefore, false)->increment($positionColumn);
            $newEntityPosition = $newPosition;
            $newPositionEntityPosition = $newPosition + 1;
        } elseif ($oldPosition < $newPosition) {
            $this->queryBetween($oldPosition, $newPosition, false, !$isMoveBefore)->decrement($positionColumn);
            $newEntityPosition = $newPosition - 1;
            $newPositionEntityPosition = $newPosition;
        } else {
            return;
        }

        if ($isMoveBefore) {
            $entity->pivot->$positionColumn = $newEntityPosition;
            $positionEntity->pivot->$positionColumn = $newPositionEntityPosition;
        } else {
            $entity->pivot->$positionColumn = $newEntityPosition + 1;
            $positionEntity->pivot->$positionColumn = $newPositionEntityPosition - 1;
        }

        $entity->pivot->save();
        $positionEntity->pivot->save();
    }

    /**
     * @param $left
     * @param $right
     * @param $leftIncluded
     * @param $rightIncluded
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function queryBetween($left, $right, $leftIncluded = false, $rightIncluded = false)
    {
        $positionColumn = $this->getOrderColumnName();

        $leftOperator = $leftIncluded ? '>=' : '>';
        $rightOperator = $rightIncluded ? '<=' : '<';

        $query = $this->newPivotQuery();

        return $query->where($positionColumn, $leftOperator, $left)->where($positionColumn, $rightOperator, $right);
    }

    /**
     * Get position of new relation.
     *
     * @return float
     */
    protected function getNextPosition()
    {
        return 1 + $this->newPivotQuery()->max($this->getOrderColumnName());
    }

    /**
     * get position column name.
     *
     * @return string
     */
    protected function getOrderColumnName()
    {
        return $this->orderColumn;
    }
}
