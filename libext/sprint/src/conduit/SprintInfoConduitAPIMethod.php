<?php

final class SprintInfoConduitAPIMethod extends SprintManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'sprint.info';
  }

  public function getMethodDescription() {
    return pht('Retrieve points and info for a Maniphest task, given its ID.');
  }

  protected function defineParamTypes() {
    return array(
        'task_id' => 'required id',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
        'ERR_BAD_TASK' => pht('No such Maniphest task exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task_id = $request->getValue('task_id');

    $task = id(new ManiphestTaskQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($task_id))
        ->needSubscriberPHIDs(true)
        ->needProjectPHIDs(true)
        ->executeOne();
    if (!$task) {
      throw new ConduitException('ERR_BAD_TASK');
    }

    return $this->buildTaskInfoDictionary($task);
  }

}
