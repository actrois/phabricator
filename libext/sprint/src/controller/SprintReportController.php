<?php

final class SprintReportController extends SprintController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    if ($request->isFormPost()) {
      $uri = $request->getRequestURI();

      $project = head($request->getArr('set_project'));
      $project = nonempty($project, null);
      $uri = $uri->alter('project', $project);

      $window = $request->getStr('set_window');
      $uri = $uri->alter('window', $window);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $nav = $this->buildNavMenu();
    $this->view = $nav->selectFilter($this->view, 'List');


    switch ($this->view) {
      case 'list':
      case 'user':
      case 'project':
      $core = id(new SprintReportOpenTasksView())
          ->setViewer($viewer)
          ->setRequest($request)
          ->setView($this->view);
        break;
      case 'burn':
        $core = id(new SprintReportBurnUpView())
            ->setViewer($viewer)
            ->setRequest($request);
        break;
      case 'history':
        $core = id(new SprintHistoryTableView())
            ->setViewer($viewer)
            ->setRequest($request);
        break;
      default:
        return new Aphront404Response();
    }

    $can_create = $this->hasApplicationCapability(
        ProjectCreateProjectsCapability::CAPABILITY);
    $nav->appendChild($core);
    $nav->setCrumbs(
        $this->buildSprintApplicationCrumbs($can_create)
            ->setBorder(true)
            ->addTextCrumb(pht('Reports')));
    $title = pht('Sprint Reports');
    return $this->newPage()
        ->setTitle($title)
        ->appendChild($nav);
  }
}
