<?php

final class ManiphestTaskListView extends ManiphestView {

  private $tasks;
  private $handles;
  private $showBatchControls;
  private $showSubpriorityControls;

  public function setTasks(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');
    $this->tasks = $tasks;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function setShowSubpriorityControls($show_subpriority_controls) {
    $this->showSubpriorityControls = $show_subpriority_controls;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    require_celerity_resource('maniphest-task-summary-css');

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $color_map = ManiphestTaskPriority::getColorMap();

    if ($this->showBatchControls) {
      Javelin::initBehavior('maniphest-list-editor');
    }

    foreach ($this->tasks as $task) {
      $item = new PHUIObjectItemView();
      $item->setObjectName('T'.$task->getID());
      $field_list = PhabricatorCustomField::getObjectFields(
        $task,
        PhabricatorCustomField::ROLE_VIEW);
      $field_list
        ->setViewer($this->getUser())
        ->readFieldsFromStorage($task);
      $completion_date = null;
      foreach ($field_list->getFields() as $key => $field) {
        if ($key == 'std:maniphest:bnch:completion-date') {
          $value = $field->renderPropertyViewValue([]);
          $completion_date = $value;
        }
      }
      if ($completion_date) {
        $header = '['.$completion_date.'] '.$task->getTitle();
      } else {
        $header = $task->getTitle();
      }
      $item->setHeader($header);
      $item->setHref('/T'.$task->getID());

      if ($task->getOwnerPHID()) {
        $owner = $handles[$task->getOwnerPHID()];
        $item->addByline(pht('Assigned: %s', $owner->renderLink()));
      }

      $status = $task->getStatus();
      if ($task->isClosed()) {
        $item->setDisabled(true);
      }

      $item->setBarColor(idx($color_map, $task->getPriority(), 'grey'));

      $item->addIcon(
        'none',
        phabricator_datetime($task->getDateModified(), $this->getUser()));

      if ($this->showSubpriorityControls) {
        $item->setGrippable(true);
      }
      if ($this->showSubpriorityControls || $this->showBatchControls) {
        $item->addSigil('maniphest-task');
      }

      $project_handles = array_select_keys(
        $handles,
        $task->getProjectPHIDs());

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Projects'))
          ->setSlim(true)
          ->setHandles($project_handles));

      $item->setMetadata(
        array(
          'taskID' => $task->getID(),
        ));

      if ($this->showBatchControls) {
        $href = new PhutilURI('/maniphest/task/edit/'.$task->getID().'/');
        if (!$this->showSubpriorityControls) {
          $href->setQueryParam('ungrippable', 'true');
        }
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
            ->addSigil('maniphest-edit-task')
            ->setHref($href));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public static function loadTaskHandles(
    PhabricatorUser $viewer,
    array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');

    $phids = array();
    foreach ($tasks as $task) {
      $assigned_phid = $task->getOwnerPHID();
      if ($assigned_phid) {
        $phids[] = $assigned_phid;
      }
      foreach ($task->getProjectPHIDs() as $project_phid) {
        $phids[] = $project_phid;
      }
    }

    if (!$phids) {
      return array();
    }

    return id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
  }

}
