<?php

final class SprintColumnTransaction {

  private $viewer;
  private $project;
  private $query;
  private $tasks;
  private $events;
  private $xquery;

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setProject($project) {
    $this->project = $project;
    return $this;
  }

  public function setQuery($query) {
    $this->query = $query;
    return $this;
  }

  public function setTasks($tasks) {
    $this->tasks = $tasks;
    return $this;
  }

  public function getXactionsforColumn(
      PhabricatorApplicationTransactionInterface $column) {
    $xactions = $this->xquery
        ->setViewer($this->viewer)
        ->withObjectPHIDs(array($column->getPHID()))
        ->needComments(true)
        ->setReversePaging(false)
        ->execute();
    $xactions = array_reverse($xactions);
    return $xactions;
  }

  public function parseEvents($dates) {

    $sprintpoints = id(new SprintPoints())
        ->setTasks($this->tasks)
        ->setViewer($this->viewer);

    foreach ($this->events as $event) {
      $modify_date = $event['modified'];
      $task_phid = $event['objectPHID'];

      $points = $sprintpoints->getTaskPointsbyPHID($task_phid);

      $date = phabricator_format_local_time($modify_date,
          $this->viewer, 'D M j');

       switch ($event['type']) {
          case 'close':
            $this->closeTasksToday($date, $dates);
            $this->closePointsToday($date, $points, $dates);
            break;
          case 'reopen':
            $this->reopenedTasksToday($date, $dates);
           // $this->reopenedPointsToday($date, $points, $dates);
            break;
        }
      }
    return $dates;
  }

  private function closeTasksToday($date, $dates) {
    $dates[$date]->setTasksClosedToday();
    return $dates;
  }

  private function closePointsToday($date, $points, $dates) {
    $dates[$date]->setPointsClosedToday($points);
    return $dates;
  }

  private function reopenedPointsToday($date, $points, $dates) {
    $dates[$date]->setPointsReopenedToday($points);
    return $dates;
  }

  private function reopenedTasksToday($date, $dates) {
    $dates[$date]->setTasksReopenedToday();
    return $dates;
  }

  private function setXActionEventType($old_col_name, $new_col_name) {
    $old_is_closed = ($old_col_name = SprintConstants::TYPE_CLOSED_STATUS_COLUMN and $new_col_name != SprintConstants::TYPE_CLOSED_STATUS_COLUMN);

    if ($old_is_closed) {
      return 'reopen';
    } else if ($new_col_name) {
      switch ($new_col_name) {
          case SprintConstants::TYPE_CLOSED_STATUS_COLUMN:
              return 'close';
          case SprintConstants::TYPE_REVIEW_STATUS_COLUMN:
              return 'review';
          case SprintConstants::TYPE_DOING_STATUS_COLUMN:
              return 'doing';
          case SprintConstants::TYPE_BACKLOG_STATUS_COLUMN:
              return 'backlog';
          default:
              break;
      }
    } else {
    return null;
    }
  }

  public function setEvents($xactions) {
    assert_instances_of($xactions, 'ManiphestTransaction');
    $old_col_name = null;
    $new_col_name = null;
    $events = array();
    foreach ($xactions as $xaction) {
      $oldval = $xaction->getOldValue();
      if (!empty($oldval)) {
        $newArr = call_user_func_array('array_merge', $oldval);
        $old_col_phid = idx($newArr, 'columnPHID');
        foreach ($old_col_phid as $phid) {
          $old_col = $this->query->getColumnforPHID($phid);
          foreach ($old_col as $obj) {
            $old_col_name = $obj->getDisplayName();
          }
        }
      }
      $newval = $xaction->getNewValue();
      if (!empty($newval)) {
        $newArr = call_user_func_array('array_merge', $newval);
        $new_col_phid = idx($newArr, 'columnPHID');
        $xaction_scope_phid = idx($newArr, 'boardPHID');
          $new_col = $this->query->getColumnforPHID($new_col_phid);
          foreach ($new_col as $obj) {
            $new_col_name = $obj->getDisplayName();
          }
      }
      $scope_phid = $this->project->getPHID();
      if ($scope_phid == $xaction_scope_phid) {
        $event_type = $this->setXActionEventType($old_col_name, $new_col_name);
        if ($event_type !== null) {
          $events[] = array(
              'transactionPHID' => $xaction->getPHID(),
              'objectPHID' => $xaction->getObjectPHID(),
              'created' => $xaction->getDateCreated(),
              'modified' => $xaction->getDateModified(),
              'key'   => $xaction->getMetadataValue('customfield:key'),
              'type'  => $event_type,
              'title' => $xaction->getTitle(),
          );
        }
      }
    }

    $this->events = isort($events, 'modified');

    return $this;
  }
}
