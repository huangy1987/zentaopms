<?php
/**
 * The control file of project module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     project
 * @version     $Id: control.php 5094 2013-07-10 08:46:15Z chencongzhi520@gmail.com $
 * @link        http://www.zentao.net
 */
class project extends control
{
    public $projects;

    /**
     * Construct function, Set projects.
     *
     * @access public
     * @return void
     */
    public function __construct($moduleName = '', $methodName = '')
    {
        parent::__construct($moduleName, $methodName);
        if($this->methodName != 'computeburn')
        {
            $this->projects = $this->project->getPairs('nocode');
            if(!$this->projects and $this->methodName != 'create' and $this->app->getViewType() != 'mhtml') $this->locate($this->createLink('project', 'create'));
        }
    }

    /**
     * The index page.
     *
     * @param  string $locate     yes|no locate to the browse page or not.
     * @param  string $status     the projects status, if locate is no, then get projects by the $status.
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function index($locate = 'yes', $status = 'undone', $projectID = 0, $orderBy = 'order_desc', $productID = 0, $recTotal = 0, $recPerPage = 10, $pageID = 1)
    {
        if($locate == 'yes') $this->locate($this->createLink('project', 'task'));

        if($this->projects) $this->commonAction($projectID);
        $this->session->set('projectList', $this->app->getURI(true));

        /* Load pager and get tasks. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $this->app->loadLang('my');
        $this->view->title         = $this->lang->project->allProject;
        $this->view->position[]    = $this->lang->project->allProject;
        $this->view->projectStats  = $this->project->getProjectStats($status == 'byproduct' ? 'all' : $status, $productID, 0, 30, $orderBy, $pager);
        $this->view->products      = array(0 => $this->lang->product->select) + $this->loadModel('product')->getPairs();
        $this->view->productID     = $productID;
        $this->view->projectID     = $projectID;
        $this->view->pager         = $pager;
        $this->view->recTotal      = $pager->recTotal;
        $this->view->recPerPage    = $pager->recPerPage;
        $this->view->orderBy       = $orderBy;
        $this->view->users         = $this->loadModel('user')->getPairs('noletter');
        $this->view->status        = $status;

        $this->display();
    }

    /**
     * Browse a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function browse($projectID = 0)
    {
        $this->locate($this->createLink($this->moduleName, 'task', "projectID=$projectID"));
    }

    /**
     * Common actions.
     *
     * @param  int    $projectID
     * @access public
     * @return object current object
     */
    public function commonAction($projectID = 0, $extra = '')
    {
        $this->loadModel('product');

        /* Get projects and products info. */
        $projectID     = $this->project->saveState($projectID, array_keys($this->projects));
        $project       = $this->project->getById($projectID);
        $products      = $this->project->getProducts($project->id);
        $childProjects = $this->project->getChildProjects($project->id);
        $teamMembers   = $this->project->getTeamMembers($project->id);
        $actions       = $this->loadModel('action')->getList('project', $project->id);

        /* Set menu. */
        $this->project->setMenu($this->projects, $project->id, $extra);

        /* Assign. */
        $this->view->projects      = $this->projects;
        $this->view->project       = $project;
        $this->view->childProjects = $childProjects;
        $this->view->products      = $products;
        $this->view->teamMembers   = $teamMembers;
        $this->view->actions       = $actions;

        return $project;
    }

    /**
     * Tasks of a project.
     *
     * @param  int    $projectID
     * @param  string $status
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function task($projectID = 0, $status = 'unclosed', $param = 0, $orderBy = '', $recTotal = 0, $recPerPage = 100, $pageID = 1)
    {
        $this->loadModel('tree');
        $this->loadModel('search');
        $this->loadModel('task');

        /* Set browseType, productID, moduleID and queryID. */
        $browseType = strtolower($status);
        $queryID    = ($browseType == 'bysearch')  ? (int)$param : 0;
        $moduleID   = ($browseType == 'bymodule')  ? (int)$param : 0;
        $productID  = ($browseType == 'byproduct') ? (int)$param : 0;
        $project    = $this->commonAction($projectID, $status);
        $projectID  = $project->id;
        $products   = $this->loadModel('product')->getProductsByProject($projectID);

        /* Save to session. */
        $uri = $this->app->getURI(true);
        $this->app->session->set('taskList',    $uri);
        $this->app->session->set('storyList',   $uri);
        $this->app->session->set('projectList', $uri);

        /* Process the order by field. */
        if(!$orderBy) $orderBy = $this->cookie->projectTaskOrder ? $this->cookie->projectTaskOrder : 'status,id_desc';
        setcookie('projectTaskOrder', $orderBy, $this->config->cookieLife, $this->config->webRoot);

        /* Append id for secend sort. */
        $sort = $this->loadModel('common')->appendOrder($orderBy);

        /* Header and position. */
        $this->view->title      = $project->name . $this->lang->colon . $this->lang->project->task;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $this->view->position[] = $this->lang->project->task;

        /* Load pager and get tasks. */
        $this->app->loadClass('pager', $static = true);
        if($this->app->getViewType() == 'mhtml') $recPerPage = 10;
        $pager = new pager($recTotal, $recPerPage, $pageID);

        /* Get tasks. */
        $tasks = $this->project->getTasks($productID, $projectID, $this->projects, $status, $browseType, $queryID, $moduleID, $sort, $pager);

       /* Build the search form. */
        $actionURL = $this->createLink('project', 'task', "projectID=$projectID&status=bySearch&param=myQueryID");
        $this->project->buildSearchForm($projectID, $this->projects, $queryID, $actionURL);

        /* team member pairs. */
        $memberPairs = array();
        foreach($this->view->teamMembers as $key => $member) $memberPairs[$key] = $member->realname;

        /* Assign. */
        $this->view->tasks        = $tasks;
        $this->view->summary      = $this->project->summary($tasks);
        $this->view->tabID        = 'task';
        $this->view->pager        = $pager;
        $this->view->recTotal     = $pager->recTotal;
        $this->view->recPerPage   = $pager->recPerPage;
        $this->view->orderBy      = $orderBy;
        $this->view->browseType   = $browseType;
        $this->view->status       = $status;
        $this->view->users        = $this->loadModel('user')->getPairs('noletter');
        $this->view->param        = $param;
        $this->view->projectID    = $projectID;
        $this->view->project      = $project;
        $this->view->productID    = $productID;
        $this->view->moduleID     = $moduleID;
        $this->view->moduleTree   = $this->tree->getTaskTreeMenu($projectID, $productID = 0, $startModuleID = 0, array('treeModel', 'createTaskLink'));
        $this->view->projectTree  = $this->project->tree();
        $this->view->memberPairs  = $memberPairs;
        $this->view->branchGroups = $this->loadModel('branch')->getByProducts(array_keys($products), 'noempty');

        $this->display();
    }

    /**
     * Browse tasks in group.
     *
     * @param  int    $projectID
     * @param  string $groupBy    the field to group by
     * @access public
     * @return void
     */
    public function grouptask($projectID = 0, $groupBy = 'story')
    {
        $project   = $this->commonAction($projectID);
        $projectID = $project->id;

        /* Save session. */
        $this->app->session->set('taskList',  $this->app->getURI(true));
        $this->app->session->set('storyList', $this->app->getURI(true));

        /* Header and session. */
        $this->view->title      = $project->name . $this->lang->colon . $this->lang->project->task;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $this->view->position[] = $this->lang->project->task;

        /* Get tasks and group them. */
        $tasks       = $this->loadModel('task')->getProjectTasks($projectID, $status = 'all', $groupBy ? $groupBy : 'story');
        $groupBy     = strtolower(str_replace('`', '', $groupBy));
        $taskLang    = $this->lang->task;
        $groupByList = array();
        $groupTasks  = array();

        /* Get users. */
        $users = $this->loadModel('user')->getPairs('noletter');
        foreach($tasks as $task)
        {
            if($groupBy == '')
            {
                $groupTasks[$task->story][] = $task;
                $groupByList[$task->story]  = $task->storyTitle;
            }
            elseif($groupBy == 'story')
            {
                $groupTasks[$task->story][] = $task;
                $groupByList[$task->story]  = $task->storyTitle;
            }
            elseif($groupBy == 'status')
            {
                $groupTasks[$taskLang->statusList[$task->status]][] = $task;
            }
            elseif($groupBy == 'assignedto')
            {
                $groupTasks[$task->assignedToRealName][] = $task;
            }
            elseif($groupBy == 'openedby')
            {
                $groupTasks[$users[$task->openedBy]][] = $task;
            }
            elseif($groupBy == 'finishedby')
            {
                $groupTasks[$users[$task->finishedBy]][] = $task;
            }
            elseif($groupBy == 'closedby')
            {
                $groupTasks[$users[$task->closedBy]][] = $task;
            }
            elseif($groupBy == 'type')
            {
                $groupTasks[$taskLang->typeList[$task->type]][] = $task;
            }
            else
            {
                $groupTasks[$task->$groupBy][] = $task;
            }
        }

        /* Assign. */
        $this->view->members     = $this->project->getTeamMembers($projectID);
        $this->view->tasks       = $groupTasks;
        $this->view->tabID       = 'task';
        $this->view->groupByList = $groupByList;
        $this->view->browseType  = 'group';
        $this->view->groupBy     = $groupBy;
        $this->view->orderBy     = $groupBy;
        $this->view->projectID   = $projectID;
        $this->view->users       = $users;
        $this->display();
    }

    /**
     * Import tasks undoned from other projects.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function importTask($toProject, $fromProject = 0)
    {
        if(!empty($_POST))
        {
            $this->project->importTask($toProject,$fromProject);
            die(js::locate(inlink('importTask', "toProject=$toProject&fromProject=$fromProject"), 'parent'));
        }

        $project  = $this->commonAction($toProject);
        $branches = $this->project->getProjectBranches($toProject);
        $tasks    = $this->project->getTasks2Imported($branches);
        $projects = $this->project->getProjectsToImport(array_keys($tasks));
        unset($projects[$toProject]);
        unset($tasks[$toProject]);

        if($fromProject == 0)
        {
            $tasks2Imported = array();
            foreach($projects as $id  => $projectName)
            {
                $tasks2Imported = array_merge($tasks2Imported, $tasks[$id]);
            }
        }
        else
        {
            $tasks2Imported = $tasks[$fromProject];
        }

        /* Save session. */
        $this->app->session->set('taskList',  $this->app->getURI(true));
        $this->app->session->set('storyList', $this->app->getURI(true));

        $this->view->title          = $project->name . $this->lang->colon . $this->lang->project->importTask;
        $this->view->position[]     = html::a(inlink('browse', "projectID=$toProject"), $project->name);
        $this->view->position[]     = $this->lang->project->importTask;
        $this->view->tasks2Imported = $tasks2Imported; 
        $this->view->projects       = $projects;
        $this->view->projectID      = $project->id;
        $this->view->fromProject    = $fromProject;
        $this->display();
    }

    /**
     * Import from Bug.
     *
     * @param  int    $projectID
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function importBug($projectID = 0, $browseType = 'all', $param = 0, $recTotal = 0, $recPerPage = 30, $pageID = 1)
    {
        if(!empty($_POST))
        {
            $mails = $this->project->importBug($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            foreach($mails as $mail) $this->sendmail($mail->taskID, $mail->actionID);

            /* Locate the browser. */
            die(js::locate($this->createLink('project', 'importBug', "projectID=$projectID"), 'parent'));
        }

        /* Set browseType, productID, moduleID and queryID. */
        $browseType = strtolower($browseType);
        $queryID    = ($browseType == 'bysearch') ? (int)$param : 0;

        /* Save to session. */
        $uri = $this->app->getURI(true);
        $this->app->session->set('bugList',    $uri);
        $this->app->session->set('storyList',   $uri);
        $this->app->session->set('projectList', $uri);

        $this->loadModel('bug');
        $projects = $this->project->getPairs('nocode');
        $this->project->setMenu($projects, $projectID);

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $title      = $projects[$projectID] . $this->lang->colon . $this->lang->project->importBug;
        $position[] = html::a($this->createLink('project', 'task', "projectID=$projectID"), $projects[$projectID]);
        $position[] = $this->lang->project->importBug;

        /* Get users, products and projects.*/
        $users    = $this->project->getTeamMemberPairs($projectID, 'nodeleted');
        $products = $this->dao->select('t1.product, t2.name')->from(TABLE_PROJECTPRODUCT)->alias('t1')
            ->leftJoin(TABLE_PRODUCT)->alias('t2')
            ->on('t1.product = t2.id')
            ->where('t1.project')->eq($projectID)
            ->fetchPairs('product');
        if(!empty($products))
        {
            unset($projects);
            $projects = $this->dao->select('t1.project, t2.name')->from(TABLE_PROJECTPRODUCT)->alias('t1')
                ->leftJoin(TABLE_PROJECT)->alias('t2')
                ->on('t1.project = t2.id')
                ->where('t1.product')->in(array_keys($products))
                ->fetchPairs('project');
        }
        else
        {
            $projectName = $projects[$projectID];
            unset($projects);
            $projects[$projectID] = $projectName;
        }

        /* Get bugs.*/
        $bugs = array();
        if($browseType != "bysearch")
        {
            $bugs = $this->bug->getActiveAndPostponedBugs(array_keys($products), $projectID, $pager);
        }
        else
        {
            if($queryID)
            {
                $query = $this->loadModel('search')->getQuery($queryID);
                if($query)
                {
                    $this->session->set('importBugQuery', $query->sql);
                    $this->session->set('importBugForm', $query->form);
                }
                else
                {
                    $this->session->set('importBugQuery', ' 1 = 1');
                }
            }
            else
            {
                if($this->session->importBugQuery == false) $this->session->set('importBugQuery', ' 1 = 1');
            }
            $bugQuery = str_replace("`product` = 'all'", "`product`" . helper::dbIN(array_keys($products)), $this->session->importBugQuery); // Search all project.
            $bugs = $this->project->getSearchBugs($products, $projectID, $bugQuery, $pager, 'id_desc');
        }

       /* Build the search form. */
        $this->config->bug->search['actionURL'] = $this->createLink('project', 'importBug', "projectID=$projectID&browseType=bySearch&param=myQueryID");
        $this->config->bug->search['queryID']   = $queryID;
        if(!empty($products))
        {
            $this->config->bug->search['params']['product']['values'] = array(''=>'') + $products + array('all'=>$this->lang->project->aboveAllProduct);
        }
        else
        {
            $this->config->bug->search['params']['product']['values'] = array(''=>'');
        }
        $this->config->bug->search['params']['project']['values'] = array(''=>'') + $projects + array('all'=>$this->lang->project->aboveAllProject);
        $this->config->bug->search['params']['plan']['values']    = $this->loadModel('productplan')->getPairs(array_keys($products));
        $this->config->bug->search['module'] = 'importBug';
        $this->config->bug->search['params']['confirmed']['values'] = array('' => '') + $this->lang->bug->confirmedList;
        $this->config->bug->search['params']['module']['values']  = $this->loadModel('tree')->getOptionMenu($projectID, $viewType = 'bug', $startModuleID = 0);
        unset($this->config->bug->search['fields']['resolvedBy']);
        unset($this->config->bug->search['fields']['closedBy']);
        unset($this->config->bug->search['fields']['status']);
        unset($this->config->bug->search['fields']['toTask']);
        unset($this->config->bug->search['fields']['toStory']);
        unset($this->config->bug->search['fields']['severity']);
        unset($this->config->bug->search['fields']['resolution']);
        unset($this->config->bug->search['fields']['resolvedBuild']);
        unset($this->config->bug->search['fields']['resolvedDate']);
        unset($this->config->bug->search['fields']['closedDate']);
        unset($this->config->bug->search['fields']['branch']);
        unset($this->config->bug->search['params']['resolvedBy']);
        unset($this->config->bug->search['params']['closedBy']);
        unset($this->config->bug->search['params']['status']);
        unset($this->config->bug->search['params']['toTask']);
        unset($this->config->bug->search['params']['toStory']);
        unset($this->config->bug->search['params']['severity']);
        unset($this->config->bug->search['params']['resolution']);
        unset($this->config->bug->search['params']['resolvedBuild']);
        unset($this->config->bug->search['params']['resolvedDate']);
        unset($this->config->bug->search['params']['closedDate']);
        unset($this->config->bug->search['params']['branch']);
        $this->loadModel('search')->setSearchParams($this->config->bug->search);

        /* Assign. */
        $this->view->title      = $title;
        $this->view->position   = $position;
        $this->view->pager      = $pager;
        $this->view->bugs       = $bugs;
        $this->view->recTotal   = $pager->recTotal;
        $this->view->recPerPage = $pager->recPerPage;
        $this->view->browseType = $browseType;
        $this->view->param      = $param;
        $this->view->users      = $users;
        $this->view->projectID  = $projectID;
        $this->display();
    }

    /**
     * Browse stories of a project.
     *
     * @param  int    $projectID
     * @param  string $orderBy
     * @access public
     * @return void
     */
    public function story($projectID = 0, $orderBy = '', $type = 'byModule', $param = 0, $recTotal = 0, $recPerPage = 50, $pageID = 1)
    {
        /* Load these models. */
        $this->loadModel('story');
        $this->loadModel('user');
        $this->loadModel('task');
        $this->app->loadLang('testcase');

        /* Save session. */
        $this->app->session->set('storyList', $this->app->getURI(true));

        /* Process the order by field. */
        if(!$orderBy) $orderBy = $this->cookie->projectStoryOrder ? $this->cookie->projectStoryOrder : 'pri';
        setcookie('projectStoryOrder', $orderBy, $this->config->cookieLife, $this->config->webRoot);

        /* Append id for secend sort. */
        $sort = $this->loadModel('common')->appendOrder($orderBy);

        $project = $this->commonAction($projectID);

        /* Header and position. */
        $title      = $project->name . $this->lang->colon . $this->lang->project->story;
        $position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $position[] = $this->lang->project->story;

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        /* The pager. */
        $stories    = $this->story->getProjectStories($projectID, $sort, $type, $param, $pager);
        $this->loadModel('common')->saveQueryCondition($this->dao->get(), 'story', false);
        $storyTasks = $this->task->getStoryTaskCounts(array_keys($stories), $projectID);
        $users      = $this->user->getPairs('noletter');

        /* Save storyIDs session for get the pre and next story. */
        $storyIDs = '';
        foreach($stories as $story) $storyIDs .= ',' . $story->id;
        $this->session->set('storyIDs', $storyIDs . ',');

        /* Get project's product. */
        $productID = 0;
        $products = $this->loadModel('product')->getProductsByProject($projectID);
        if($products) $productID = key($products);

        /* Assign. */
        $this->view->title        = $title;
        $this->view->position     = $position;
        $this->view->productID    = $productID;
        $this->view->stories      = $stories;
        $this->view->summary      = $this->product->summary($stories);
        $this->view->orderBy      = $orderBy;
        $this->view->type         = $type;
        $this->view->param        = $param;
        $this->view->storyTasks   = $storyTasks;
        $this->view->moduleTree   = $this->loadModel('tree')->getProjectStoryTreeMenu($projectID, $startModuleID = 0, array('treeModel', 'createProjectStoryLink'));
        $this->view->tabID        = 'story';
        $this->view->users        = $users;
        $this->view->pager        = $pager;
        $this->view->branchGroups = $this->loadModel('branch')->getByProducts(array_keys($products), 'noempty');

        $this->display();
    }

    /**
     * Browse bugs of a project.
     *
     * @param  int    $projectID
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function bug($projectID = 0, $orderBy = 'status,id_desc', $build = 0, $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        /* Load these two models. */
        $this->loadModel('bug');
        $this->loadModel('user');

        /* Save session. */
        $this->session->set('bugList', $this->app->getURI(true));

        $project   = $this->commonAction($projectID);
        $products  = $this->project->getProducts($project->id);
        $productID = key($products);    // Get the first product for creating bug.
        $branchID  = isset($products[$productID]) ? $products[$productID]->branch : 0;

        /* Header and position. */
        $title      = $project->name . $this->lang->colon . $this->lang->project->bug;
        $position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $position[] = $this->lang->project->bug;

        /* Load pager and get bugs, user. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);
        $sort  = $this->loadModel('common')->appendOrder($orderBy);
        $bugs  = $this->bug->getProjectBugs($projectID, $sort, $pager, $build);
        $users = $this->user->getPairs('noletter');

        /* team member pairs. */
        $memberPairs = array();
        $memberPairs[] = "";
        foreach($this->view->teamMembers as $key => $member)
        {
            $memberPairs[$key] = $member->realname;
        }

        /* Assign. */
        $this->view->title       = $title;
        $this->view->position    = $position;
        $this->view->bugs        = $bugs;
        $this->view->tabID       = 'bug';
        $this->view->build       = $this->loadModel('build')->getById($build);
        $this->view->buildID     = $this->view->build ? $this->view->build->id : 0;
        $this->view->pager       = $pager;
        $this->view->orderBy     = $orderBy;
        $this->view->users       = $users;
        $this->view->productID   = $productID;
        $this->view->branchID    = empty($this->view->build->branch) ? $branchID : $this->view->build->branch;
        $this->view->memberPairs = $memberPairs;

        $this->display();
    }

    /**
     * Browse builds of a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function build($projectID = 0)
    {
        $this->loadModel('testtask');
        $this->session->set('buildList', $this->app->getURI(true));

        $project = $this->commonAction($projectID);

        /* Header and position. */
        $this->view->title      = $project->name . $this->lang->colon . $this->lang->project->build;
        $this->view->position[] = html::a(inlink('browse', "projectID=$projectID"), $project->name);
        $this->view->position[] = $this->lang->project->build;

        /* Get builds. */
        $this->view->builds = $this->loadModel('build')->getProjectBuilds((int)$projectID);
        $this->view->users  = $this->loadModel('user')->getPairs('noletter');

        $this->display();
    }

    /**
     * Browse test tasks of project.
     *
     * @param  int    $projectID
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function testtask($projectID = 0, $orderBy = 'id_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        $this->loadModel('testtask');
        /* Save session. */
        $this->session->set('testtaskList', $this->app->getURI(true));

        $project = $this->commonAction($projectID);

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = pager::init($recTotal, $recPerPage, $pageID);

        $this->view->title       = $this->projects[$projectID] . $this->lang->colon . $this->lang->testtask->common;
        $this->view->position[]  = html::a($this->createLink('project', 'testtask', "projectID=$projectID"), $this->projects[$projectID]);
        $this->view->position[]  = $this->lang->testtask->common;
        $this->view->projectID   = $projectID;
        $this->view->projectName = $this->projects[$projectID];
        $this->view->pager       = $pager;
        $this->view->orderBy     = $orderBy;
        $this->view->tasks       = $this->testtask->getProjectTasks($projectID);
        $this->view->users       = $this->loadModel('user')->getPairs('noclosed|noletter');

        $this->display();
    }

    /**
     * Browse burndown chart of a project.
     *
     * @param  int       $projectID
     * @param  string    $type
     * @param  int       $interval
     * @access public
     * @return void
     */
    public function burn($projectID = 0, $type = 'noweekend', $interval = 0)
    {
        $this->loadModel('report');
        $project     = $this->commonAction($projectID);
        $projectInfo = $this->project->getByID($project->id);

        /* Header and position. */
        $title      = $project->name . $this->lang->colon . $this->lang->project->burn;
        $position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $position[] = $this->lang->project->burn;

        /* Get date list. */
        list($dateList, $interval) = $this->project->getDateList($projectInfo->begin, $projectInfo->end, $type, $interval, 'Y-m-d');

        $sets          = $this->project->getBurnDataFlot($project->id);
        $limitJSON     = '[]';
        $baselineJSON  = '[]';

        $firstBurn    = empty($sets) ? 0 : reset($sets);
        $firstTime    = isset($firstBurn->value) ? $firstBurn->value : $this->project->getTotalEstimate($projectInfo->id);
        $days         = count($dateList) - 1;
        $rate         = round($firstTime / $days, 2);
        $baselineJSON = '[';
        foreach($dateList as $i => $date) $baselineJSON .= ($days - $i) * $rate . ',';
        $baselineJSON = rtrim($baselineJSON, ',') . ']';

        $chartData['labels']   = $this->report->convertFormat($dateList, DT_DATE4);
        $chartData['burnLine'] = $this->report->createSingleJSON($sets, $dateList);
        $chartData['baseLine'] = $baselineJSON;

        /* Set a space when assemble the string for english. */
        $space   = $this->app->getClientLang() == 'en' ? ' ' : '';
        $dayList = array_fill(1, floor($project->days / $this->config->project->maxBurnDay) + 5, '');
        foreach($dayList as $key => $val) $dayList[$key] = $this->lang->project->interval . $space . ($key + 1) . $space . $this->lang->day;

        /* Assign. */
        $this->view->title       = $title;
        $this->view->position    = $position;
        $this->view->tabID       = 'burn';
        $this->view->projectID   = $projectID;
        $this->view->projectName = $project->name;
        $this->view->type        = $type;
        $this->view->interval    = $interval;
        $this->view->chartData   = $chartData;
        $this->view->dayList     = array('full' => $this->lang->project->interval . $space . 1 . $space . $this->lang->day) + $dayList;

        $this->display();
    }

    /**
     * Get data of burndown chart.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function burnData($projectID = 0)
    {
        $this->loadModel('report');
        $sets = $this->project->getBurnData($projectID);
        die($this->report->createSingleXML($sets, $this->lang->project->charts->burn->graph));
    }

    /**
     * Compute burndown datas.
     *
     * @param  string $reload
     * @access public
     * @return void
     */
    public function computeBurn($reload = 'no')
    {
        $this->view->burns = $this->project->computeBurn();
        if($reload == 'yes') die(js::reload('parent'));
        $this->display();
    }

    /**
     * Fix burn for first date.
     * 
     * @param  int    $projectID 
     * @access public
     * @return void
     */
    public function fixFirst($projectID)
    {
        if($_POST)
        {
            $this->project->fixFirst($projectID);
            die(js::reload('parent.parent'));
        }

        $project = $this->project->getById($projectID);

        $this->view->firstBurn = $this->dao->select('*')->from(TABLE_BURN)->where('project')->eq($projectID)->andWhere('date')->eq($project->begin)->fetch('left');
        $this->view->project   = $project;
        $this->display();
    }

    /**
     * Browse team of a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function team($projectID = 0)
    {
        $project = $this->commonAction($projectID);

        $title      = $project->name . $this->lang->colon . $this->lang->project->team;
        $position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $position[] = $this->lang->project->team;

        $this->view->title    = $title;
        $this->view->position = $position;

        $this->display();
    }

    /**
     * Docs of a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function doc($projectID)
    {
        /* use first project if projectID does not exist. */
        if(!isset($this->projects[$projectID])) $projectID = key($this->projects);

        $this->project->setMenu($this->projects, $projectID);
        $this->session->set('docList', $this->app->getURI(true));

        $project = $this->dao->findById($projectID)->from(TABLE_PROJECT)->fetch();
        $this->view->title      = $project->name . $this->lang->colon . $this->lang->project->doc;
        $this->view->position[] = html::a($this->createLink($this->moduleName, 'browse'), $project->name);
        $this->view->position[] = $this->lang->project->doc;
        $this->view->project    = $project;
        $this->view->docs       = $this->loadModel('doc')->getProjectDocs($projectID);
        $this->view->modules    = $this->doc->getProjectModulePairs();
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Create a project.
     *
     * @access public
     * @return void
     */
    public function create($projectID = '', $copyProjectID = '')
    {
        if($projectID)
        {
            $this->view->title     = $this->lang->project->tips;
            $this->view->tips      = $this->fetch('project', 'tips', "projectID=$projectID");
            $this->view->projectID = $projectID;
            $this->display();
            exit;
        }

        $name      = '';
        $code      = '';
        $team      = '';
        $products  = array();
        $whitelist = '';
        $acl       = 'open';

        if($copyProjectID)
        {
            $copyProject = $this->dao->select('*')->from(TABLE_PROJECT)->where('id')->eq($copyProjectID)->fetch();
            $name        = $copyProject->name;
            $code        = $copyProject->code;
            $team        = $copyProject->team;
            $acl         = $copyProject->acl;
            $whitelist   = $copyProject->whitelist;
            $products    = $this->project->getProducts($copyProjectID);
        }

        if(!empty($_POST))
        {
            $projectID = $copyProjectID == '' ? $this->project->create() : $this->project->create($copyProjectID);
            $this->project->updateProducts($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            $this->loadModel('action')->create('project', $projectID, 'opened');
            die(js::locate($this->createLink('project', 'create', "projectID=$projectID"), 'parent'));
        }

        $this->project->setMenu($this->projects, key($this->projects));

        $this->view->title         = $this->lang->project->create;
        $this->view->position[]    = $this->view->title;
        $this->view->projects      = array('' => '') + $this->projects;
        $this->view->groups        = $this->loadModel('group')->getPairs();
        $this->view->allProducts   = array(0 => '') + $this->loadModel('product')->getPairs('noclosed|nocode');
        $this->view->name          = $name;
        $this->view->code          = $code;
        $this->view->team          = $team;
        $this->view->products      = $products ;
        $this->view->whitelist     = $whitelist;
        $this->view->acl           = $acl      ;
        $this->view->copyProjectID = $copyProjectID;
        $this->view->branchGroups  = $this->loadModel('branch')->getByProducts(array_keys($products));
        $this->display();
    }

    /**
     * Edit a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function edit($projectID, $action = 'edit', $extra = '')
    {
        $browseProjectLink = $this->createLink('project', 'browse', "projectID=$projectID");
        if(!empty($_POST))
        {
            $changes = $this->project->update($projectID);
            $this->project->updateProducts($projectID);
            if(dao::isError()) die(js::error(dao::getError()));
            if($action == 'undelete')
            {
                $this->loadModel('action');
                $this->dao->update(TABLE_PROJECT)->set('deleted')->eq(0)->where('id')->eq($projectID)->exec();
                $this->dao->update(TABLE_ACTION)->set('extra')->eq(ACTIONMODEL::BE_UNDELETED)->where('id')->eq($extra)->exec();
                $this->action->create('project', $projectID, 'undeleted');
            }
            if($changes)
            {
                $actionID = $this->loadModel('action')->create('project', $projectID, 'edited');
                $this->action->logHistory($actionID, $changes);
            }
            die(js::locate($this->createLink('project', 'view', "projectID=$projectID"), 'parent'));
        }

        /* Set menu. */
        $this->project->setMenu($this->projects, $projectID);

        $projects = array('' => '') + $this->projects;
        $project  = $this->project->getById($projectID);
        $managers = $this->project->getDefaultManagers($projectID);

        /* Remove current project from the projects. */
        unset($projects[$projectID]);

        $title      = $this->lang->project->edit . $this->lang->colon . $project->name;
        $position[] = html::a($browseProjectLink, $project->name);
        $position[] = $this->lang->project->edit;

        $allProducts    = array(0 => '') + $this->loadModel('product')->getPairs('noclosed|nocode');
        $linkedProducts = $this->project->getProducts($project->id);
        foreach($linkedProducts as $product)
        {
            if(!isset($allProducts[$product->id])) $allProducts[$product->id] = $product->name;
        }

        $this->view->title          = $title;
        $this->view->position       = $position;
        $this->view->projects       = $projects;
        $this->view->project        = $project;
        $this->view->poUsers        = $this->loadModel('user')->getPairs('noclosed,nodeleted,pofirst', $project->PO);
        $this->view->pmUsers        = $this->user->getPairs('noclosed,nodeleted,pmfirst',  $project->PM);
        $this->view->qdUsers        = $this->user->getPairs('noclosed,nodeleted,qdfirst',  $project->QD);
        $this->view->rdUsers        = $this->user->getPairs('noclosed,nodeleted,devfirst', $project->RD);
        $this->view->groups         = $this->loadModel('group')->getPairs();
        $this->view->allProducts    = $allProducts;
        $this->view->linkedProducts = $linkedProducts;
        $this->view->branchGroups   = $this->loadModel('branch')->getByProducts(array_keys($linkedProducts));

        $this->display();
    }

    /**
     * Batch edit.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function batchEdit($projectID = 0)
    {
        if($this->post->names)
        {
            $allChanges = $this->project->batchUpdate();
            if(!empty($allChanges))
            {
                foreach($allChanges as $projectID => $changes)
                {
                    if(empty($changes)) continue;

                    $actionID = $this->loadModel('action')->create('project', $projectID, 'Edited');
                    $this->action->logHistory($actionID, $changes);
                }
            }
            die(js::locate($this->session->projectList, 'parent'));
        }

        $this->project->setMenu($this->projects, $projectID);

        $projectIDList = $this->post->projectIDList ? $this->post->projectIDList : die(js::locate($this->session->projectList, 'parent'));

        $this->view->title         = $this->lang->project->batchEdit;
        $this->view->position[]    = $this->lang->project->batchEdit;
        $this->view->projectIDList = $projectIDList;
        $this->view->projects      = $this->dao->select('*')->from(TABLE_PROJECT)->where('id')->in($projectIDList)->fetchAll('id');
        $this->view->pmUsers       = $this->loadModel('user')->getPairs('noclosed,nodeleted,pmfirst');
        $this->display();
    }

    /**
     * Start project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function start($projectID)
    {
        $this->commonAction($projectID);

        if(!empty($_POST))
        {
            $this->loadModel('action');
            $changes = $this->project->start($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            if($this->post->comment != '' or !empty($changes))
            {
                $actionID = $this->action->create('project', $projectID, 'Started', $this->post->comment);
                $this->action->logHistory($actionID, $changes);
            }
            die(js::reload('parent.parent'));
        }

        $this->view->title      = $this->view->project->name . $this->lang->colon .$this->lang->project->start;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $this->view->project->name);
        $this->view->position[] = $this->lang->project->start;
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Delay project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function putoff($projectID)
    {
        $this->commonAction($projectID);

        if(!empty($_POST))
        {
            $this->loadModel('action');
            $changes = $this->project->putoff($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            if($this->post->comment != '' or !empty($changes))
            {
                $actionID = $this->action->create('project', $projectID, 'Delayed', $this->post->comment);
                $this->action->logHistory($actionID, $changes);
            }
            die(js::reload('parent.parent'));
        }

        $this->view->title      = $this->view->project->name . $this->lang->colon .$this->lang->project->putoff;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $this->view->project->name);
        $this->view->position[] = $this->lang->project->putoff;
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Suspend project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function suspend($projectID)
    {
        $this->commonAction($projectID);

        if(!empty($_POST))
        {
            $this->loadModel('action');
            $changes = $this->project->suspend($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            if($this->post->comment != '' or !empty($changes))
            {
                $actionID = $this->action->create('project', $projectID, 'Suspended', $this->post->comment);
                $this->action->logHistory($actionID, $changes);
            }
            die(js::reload('parent.parent'));
        }

        $this->view->title      = $this->view->project->name . $this->lang->colon .$this->lang->project->suspend;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $this->view->project->name);
        $this->view->position[] = $this->lang->project->suspend;
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Activate project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function activate($projectID)
    {
        $this->commonAction($projectID);

        if(!empty($_POST))
        {
            $this->loadModel('action');
            $changes = $this->project->activate($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            if($this->post->comment != '' or !empty($changes))
            {
                $actionID = $this->action->create('project', $projectID, 'Activated', $this->post->comment);
                $this->action->logHistory($actionID, $changes);
            }
            die(js::reload('parent.parent'));
        }

        $this->view->title      = $this->view->project->name . $this->lang->colon .$this->lang->project->activate;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $this->view->project->name);
        $this->view->position[] = $this->lang->project->activate;
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Close project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function close($projectID)
    {
        $this->commonAction($projectID);

        if(!empty($_POST))
        {
            $this->loadModel('action');
            $changes = $this->project->close($projectID);
            if(dao::isError()) die(js::error(dao::getError()));

            if($this->post->comment != '' or !empty($changes))
            {
                $actionID = $this->action->create('project', $projectID, 'Closed', $this->post->comment);
                $this->action->logHistory($actionID, $changes);
            }
            die(js::reload('parent.parent'));
        }

        $this->view->title      = $this->view->project->name . $this->lang->colon .$this->lang->project->close;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $this->view->project->name);
        $this->view->position[] = $this->lang->project->close;
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * View a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function view($projectID)
    {
        $project = $this->project->getById($projectID, true);
        if(!$project) die(js::error($this->lang->notFound) . js::locate('back'));

        $products = $this->project->getProducts($project->id);

        /* Set menu. */
        $this->project->setMenu($this->projects, $project->id);

        $this->view->title      = $this->lang->project->view;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $this->view->position[] = $this->view->title;

        $this->view->project      = $project;
        $this->view->products     = $products;
        $this->view->branchGroups = $this->loadModel('branch')->getByProducts(array_keys($products));
        $this->view->groups       = $this->loadModel('group')->getPairs();
        $this->view->actions      = $this->loadModel('action')->getList('project', $projectID);
        $this->view->users        = $this->loadModel('user')->getPairs('noletter');

        $this->display();
    }

    /**
     * Delete a project.
     *
     * @param  int    $projectID
     * @param  string $confirm   yes|no
     * @access public
     * @return void
     */
    public function delete($projectID, $confirm = 'no')
    {
        if($confirm == 'no')
        {
            echo js::confirm(sprintf($this->lang->project->confirmDelete, $this->projects[$projectID]), $this->createLink('project', 'delete', "projectID=$projectID&confirm=yes"));
            exit;
        }
        else
        {
            $this->project->delete(TABLE_PROJECT, $projectID);
            $this->session->set('project', '');
            die(js::locate(inlink('index'), 'parent'));
        }
    }

    /**
     * Send email.
     *
     * @param  int    $taskID
     * @param  int    $actionID
     * @access public
     * @return void
     */
    public function sendmail($taskID, $actionID)
    {
        /* Reset $this->output. */
        $this->clear();

        /* Set toList and ccList. */
        $task        = $this->loadModel('task')->getById($taskID);
        $projectName = $this->project->getById($task->project)->name;
        $toList      = $task->assignedTo;
        $ccList      = trim($task->mailto, ',');

        if($toList == '')
        {
            if($ccList == '') return;
            if(strpos($ccList, ',') === false)
            {
                $toList = $ccList;
                $ccList = '';
            }
            else
            {
                $commaPos = strpos($ccList, ',');
                $toList = substr($ccList, 0, $commaPos);
                $ccList = substr($ccList, $commaPos + 1);
            }
        }
        elseif(strtolower($toList) == 'closed')
        {
            $toList = $task->finishedBy;
        }

        /* Get action info. */
        $action          = $this->loadModel('action')->getById($actionID);
        $history         = $this->action->getHistory($actionID);
        $action->history = isset($history[$actionID]) ? $history[$actionID] : array();

        /* Create the email content. */
        $this->view->task   = $task;
        $this->view->action = $action;

        $mailContent = $this->parse($this->moduleName, 'sendmail');

        /* Send emails. */
        $this->loadModel('mail')->send($toList, 'TASK#' . $task->id . ' ' . $task->name . ' - ' . $projectName, $mailContent, $ccList);
        if($this->mail->isError()) trigger_error(join("\n", $this->mail->getError()));
    }

    /**
     * Manage products.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function manageProducts($projectID)
    {
        /* use first project if projectID does not exist. */
        if(!isset($this->projects[$projectID])) $projectID = key($this->projects);

        $browseProjectLink = $this->createLink('project', 'browse', "projectID=$projectID");
        if(!empty($_POST))
        {
            $this->project->updateProducts($projectID);
            if(dao::isError()) dis(js::error(dao::getError()));
            die(js::locate($browseProjectLink));
        }

        $this->loadModel('product');
        $project  = $this->project->getById($projectID);

        /* Set menu. */
        $this->project->setMenu($this->projects, $project->id);

        /* Title and position. */
        $title      = $this->lang->project->manageProducts . $this->lang->colon . $project->name;
        $position[] = html::a($browseProjectLink, $project->name);
        $position[] = $this->lang->project->manageProducts;

        $allProducts     = $this->product->getPairs('noclosed|nocode');
        $linkedProducts  = $this->project->getProducts($project->id);
        // Merge allProducts and linkedProducts for closed product.
        foreach($linkedProducts as $product)
        {
            if(!isset($allProducts[$product->id])) $allProducts[$product->id] = $product->name;
        }

        /* Assign. */
        $this->view->title          = $title;
        $this->view->position       = $position;
        $this->view->allProducts    = $allProducts;
        $this->view->linkedProducts = $linkedProducts;
        $this->view->branchGroups   = $this->loadModel('branch')->getByProducts(array_keys($allProducts));

        $this->display();
    }

    /**
     * Manage childs projects.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function manageChilds($projectID)
    {
        $browseProjectLink = $this->createLink('project', 'browse', "projectID=$projectID");
        if(!empty($_POST))
        {
            $this->project->updateChilds($projectID);
            die(js::locate($browseProjectLink));
        }
        $project  = $this->project->getById($projectID);
        $projects = $this->projects;
        unset($projects[$projectID]);
        unset($projects[$project->parent]);
        if(empty($projects)) $this->locate($browseProjectLink);

        /* Header and position. */
        $title      = $this->lang->project->manageChilds . $this->lang->colon . $project->name;
        $position[] = html::a($browseProjectLink, $project->name);
        $position[] = $this->lang->project->manageChilds;

        $childProjects = $this->project->getChildProjects($project->id);
        $childProjects = join(",", array_keys($childProjects));

        /* Set menu. */
        $this->project->setMenu($this->projects, $project->id);

        /* Assign. */
        $this->view->title         = $title;
        $this->view->position      = $position;
        $this->view->projects      = $projects;
        $this->view->childProjects = $childProjects;

        $this->display();
    }

    /**
     * Manage members of the project.
     *
     * @param  int    $projectID
     * @param  int    $team2Import    the team to import.
     * @access public
     * @return void
     */
    public function manageMembers($projectID = 0, $team2Import = 0)
    {
        if(!empty($_POST))
        {
            $this->project->manageMembers($projectID);
            $this->locate($this->createLink('project', 'team', "projectID=$projectID"));
            exit;
        }
        $this->loadModel('user');

        $project        = $this->project->getById($projectID);
        $users          = $this->user->getPairs('noclosed, nodeleted, devfirst');
        $roles          = $this->user->getUserRoles(array_keys($users));
        $currentMembers = $this->project->getTeamMembers($projectID);
        $members2Import = $this->project->getMembers2Import($team2Import, array_keys($currentMembers));
        $teams2Import   = $this->project->getTeams2Import($this->app->user->account, $projectID);
        $teams2Import   = array($this->lang->project->copyTeam) + $teams2Import;

        /* The deleted members. */
        foreach($currentMembers as $account => $member)
        {
            if(!isset($users[$member->account])) $member->account .= $this->lang->user->deleted;
        }

        /* Set menu. */
        $this->project->setMenu($this->projects, $project->id);

        $title      = $this->lang->project->manageMembers . $this->lang->colon . $project->name;
        $position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $position[] = $this->lang->project->manageMembers;

        $this->view->title          = $title;
        $this->view->position       = $position;
        $this->view->project        = $project;
        $this->view->users          = $users;
        $this->view->roles          = $roles;
        $this->view->currentMembers = $currentMembers;
        $this->view->members2Import = $members2Import;
        $this->view->teams2Import   = $teams2Import;
        $this->view->team2Import    = $team2Import;
        $this->display();
    }

    /**
     * Unlink a memeber.
     *
     * @param  int    $projectID
     * @param  string $account
     * @param  string $confirm  yes|no
     * @access public
     * @return void
     */
    public function unlinkMember($projectID, $account, $confirm = 'no')
    {
        if($confirm == 'no')
        {
            die(js::confirm($this->lang->project->confirmUnlinkMember, $this->inlink('unlinkMember', "projectID=$projectID&account=$account&confirm=yes")));
        }
        else
        {
            $this->project->unlinkMember($projectID, $account);

            /* if ajax request, send result. */
            if($this->server->ajax)
            {
                if(dao::isError())
                {
                    $response['result']  = 'fail';
                    $response['message'] = dao::getError();
                }
                else
                {
                    $response['result']  = 'success';
                    $response['message'] = '';
                }
                $this->send($response);
            }
            die(js::locate($this->inlink('team', "projectID=$projectID"), 'parent'));
        }
    }

    /**
     * Link stories to a project.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function linkStory($projectID = 0, $browseType = '', $param = 0)
    {
        $this->loadModel('story');
        $this->loadModel('product');

        /* Get projects and products. */
        $project    = $this->project->getById($projectID);
        $products   = $this->project->getProducts($projectID);
        $browseLink = $this->createLink('project', 'story', "projectID=$projectID");

        $this->session->set('storyList', $this->app->getURI(true)); // Save session.
        $this->project->setMenu($this->projects, $project->id);     // Set menu.

        if(empty($products))
        {
            echo js::alert($this->lang->project->errorNoLinkedProducts);
            die(js::locate($this->createLink('project', 'manageproducts', "projectID=$projectID")));
        }

        if(!empty($_POST))
        {
            $this->project->linkStory($projectID);
            die(js::locate($browseLink));
        }

        $queryID = ($browseType == 'bySearch') ? (int)$param : 0;

        $branchGroups = $this->loadModel('branch')->getByProducts(array_keys($products), 'noempty');
        $branchPairs  = array();
        $productType  = 'normal';
        $productNum   = count($products);
        $branches     = array();
        foreach($products as $product)
        {
            $productPairs[$product->id] = $product->name;
            if($product->type != 'normal')
            {
                $branches[$product->branch] = $product->branch;
                $productType = $product->type;
                if($product->branch)
                {
                    $branchPairs[$product->branch] = ($productNum > 1 ? $product->name . '/' : '') . $branchGroups[$product->id][$product->branch];
                }
                else
                {
                    $productBranches = isset($branchGroups[$product->id]) ? $branchGroups[$product->id] : array(0);
                    if($productNum > 1)
                    {
                        foreach($productBranches as $branchID => $branchName) $productBranches[$branchID] = $product->name . '/' . $branchName;
                    }
                    $branchPairs += $productBranches;
                }
            }
        }

        /* Build search form. */
        unset($this->config->product->search['fields']['module']);
        $this->config->product->search['actionURL'] = $this->createLink('project', 'linkStory', "projectID=$projectID&browseType=bySearch&queryID=myQueryID");
        $this->config->product->search['queryID']   = $queryID;
        $this->config->product->search['params']['product']['values'] = $productPairs + array('all' => $this->lang->product->allProductsOfProject);
        $this->config->product->search['params']['plan']['values'] = $this->loadModel('productplan')->getForProducts($products);
        unset($this->lang->story->statusList['draft']);
        if($productType == 'normal')
        {
            unset($this->config->product->search['fields']['branch']);
            unset($this->config->product->search['params']['branch']);
        }
        else
        {
            $this->config->product->search['fields']['branch'] = sprintf($this->lang->product->branch, $this->lang->product->branchName[$productType]);
            $this->config->product->search['params']['branch']['values'] = array('' => '') + $branchPairs;
        }
        $this->config->product->search['params']['status'] = array('operator' => '=',       'control' => 'select', 'values' => $this->lang->story->statusList);

        $this->loadModel('search')->setSearchParams($this->config->product->search);

        $title      = $project->name . $this->lang->colon . $this->lang->project->linkStory;
        $position[] = html::a($browseLink, $project->name);
        $position[] = $this->lang->project->linkStory;

        if($browseType == 'bySearch')
        {
            $allStories = $this->story->getBySearch('', $queryID, 'id', null, $projectID);
        }
        else
        {
            $allStories = $this->story->getProductStories(array_keys($products), $branches, $moduleID = '0', $status = 'active');
        }
        $prjStories = $this->story->getProjectStoryPairs($projectID);

        $this->view->title        = $title;
        $this->view->position     = $position;
        $this->view->project      = $project;
        $this->view->products     = $products;
        $this->view->allStories   = $allStories;
        $this->view->prjStories   = $prjStories;
        $this->view->browseType   = $browseType;
        $this->view->productType  = $productType;
        $this->view->users        = $this->loadModel('user')->getPairs('noletter');
        $this->view->branchGroups = $branchGroups;
        $this->display();
    }

    /**
     * Unlink a story.
     *
     * @param  int    $projectID
     * @param  int    $storyID
     * @param  string $confirm    yes|no
     * @access public
     * @return void
     */
    public function unlinkStory($projectID, $storyID, $confirm = 'no')
    {
        if($confirm == 'no')
        {
            echo js::confirm($this->lang->project->confirmUnlinkStory, $this->createLink('project', 'unlinkstory', "projectID=$projectID&storyID=$storyID&confirm=yes"));
            exit;
        }
        else
        {
            $this->project->unlinkStory($projectID, $storyID);

            /* if ajax request, send result. */
            if($this->server->ajax)
            {
                if(dao::isError())
                {
                    $response['result']  = 'fail';
                    $response['message'] = dao::getError();
                }
                else
                {
                    $response['result']  = 'success';
                    $response['message'] = '';
                }
                $this->send($response);
            }
            echo js::locate($this->app->session->storyList, 'parent');
            exit;
        }
    }

    /**
     * batch unlink story.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function batchUnlinkStory($projectID)
    {
        if(isset($_POST['storyIDList']))
        {
            $storyIDList = $this->post->storyIDList;
            $_POST       = array();
            foreach($storyIDList as $storyID)
            {
                $this->project->unlinkStory($projectID, $storyID);
            }
        }
        die(js::locate($this->createLink('project', 'story', "projectID=$projectID")));
    }

    /**
     * Project dynamic.
     *
     * @param  string $type
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function dynamic($projectID = 0, $type = 'today', $param = '', $orderBy = 'date_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        /* Save session. */
        $uri   = $this->app->getURI(true);
        $this->session->set('productList',     $uri);
        $this->session->set('productPlanList', $uri);
        $this->session->set('releaseList',     $uri);
        $this->session->set('storyList',       $uri);
        $this->session->set('projectList',     $uri);
        $this->session->set('taskList',        $uri);
        $this->session->set('buildList',       $uri);
        $this->session->set('bugList',         $uri);
        $this->session->set('caseList',        $uri);
        $this->session->set('testtaskList',    $uri);

        /* use first project if projectID does not exist. */
        if(!isset($this->projects[$projectID])) $projectID = key($this->projects);

        /* Append id for secend sort. */
        $sort = $this->loadModel('common')->appendOrder($orderBy);

        /* Set the menu. If the projectID = 0, use the indexMenu instead. */
        $this->project->setMenu($this->projects, $projectID);
        if($projectID == 0)
        {
            $this->projects = array('0' => $this->lang->project->selectProject) + $this->projects;
            unset($this->lang->project->menu);
            $this->lang->project->menu = $this->lang->project->indexMenu;
            $this->lang->project->menu->list = $this->project->select($this->projects, 0, 'project', 'dynamic');
        }

        /* Set the pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = pager::init($recTotal, $recPerPage, $pageID);

        /* Set the user and type. */
        $account = $type == 'account' ? $param : 'all';
        $period  = $type == 'account' ? 'all'  : $type;

        /* The header and position. */
        $project = $this->project->getByID($projectID);
        $this->view->title      = $project->name . $this->lang->colon . $this->lang->project->dynamic;
        $this->view->position[] = html::a($this->createLink('project', 'browse', "projectID=$projectID"), $project->name);
        $this->view->position[] = $this->lang->project->dynamic;

        /* Assign. */
        $this->view->projectID = $projectID;
        $this->view->type      = $type;
        $this->view->users     = $this->loadModel('user')->getPairs('nodeleted|noletter');
        $this->view->account   = $account;
        $this->view->orderBy   = $orderBy;
        $this->view->pager     = $pager;
        $this->view->param     = $param;
        $this->view->actions   = $this->loadModel('action')->getDynamic($account, $period, $sort, $pager, 'all', $projectID);
        $this->display();
    }

    /**
     * AJAX: get products of a project in html select.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function ajaxGetProducts($projectID)
    {
        $products = $this->project->getProducts($projectID, false);
        die(html::select('product', $products, '', 'class="form-control"'));
    }

    /**
     * AJAX: get team members of the project.
     *
     * @param  int    $projectID
     * @param  string $assignedTo
     * @access public
     * @return void
     */
    public function ajaxGetMembers($projectID, $assignedTo = '')
    {
        $users      = $this->project->getTeamMemberPairs($projectID);
        $assignedTo = isset($users[$assignedTo]) ? $assignedTo : '';
        die(html::select('assignedTo', $users, $assignedTo, "class='form-control'"));
    }

    /**
     * When create a project, help the user.
     *
     * @param  int    $projectID
     * @access public
     * @return void
     */
    public function tips($projectID)
    {
        $this->view->projectID = $projectID;
        $this->display('project', 'tips');
    }

    /**
     * Drop menu page.
     *
     * @param  int    $projectID
     * @param  int    $module
     * @param  int    $method
     * @param  int    $extra
     * @access public
     * @return void
     */
    public function ajaxGetDropMenu($projectID, $module, $method, $extra)
    {
        $this->view->link      = $this->project->getProjectLink($module, $method, $extra);
        $this->view->projectID = $projectID;
        $this->view->module    = $module;
        $this->view->method    = $method;
        $this->view->extra     = $extra;
        $this->view->projects  = $this->dao->select('*')->from(TABLE_PROJECT)->where('id')->in(array_keys($this->projects))->orderBy('order desc')->fetchAll();
        $this->display();
    }

    /**
     * The results page of search.
     *
     * @param  string  $keywords
     * @param  string  $module
     * @param  string  $method
     * @param  mix     $extra
     * @access public
     * @return void
     */
    public function ajaxGetMatchedItems($keywords, $module, $method, $extra)
    {
        $projects = $this->dao->select('*')->from(TABLE_PROJECT)->where('deleted')->eq(0)->andWhere('name')->like("%$keywords%")->orderBy('order desc')->fetchAll();
        foreach($projects as $key => $project)
        {
            if(!$this->project->checkPriv($project)) unset($projects[$key]);
        }

        $this->view->link     = $this->project->getProjectLink($module, $method, $extra);
        $this->view->projects = $projects;
        $this->view->keywords = $keywords;
        $this->display();
    }

    /**
     * Update order.
     *
     * @access public
     * @return void
     */
    public function updateOrder()
    {
        $idList   = explode(',', trim($this->post->projects, ','));
        $orderBy  = $this->post->orderBy;
        if(strpos($orderBy, 'order') === false) return false;

        $projects = $this->dao->select('id,`order`')->from(TABLE_PROJECT)->where('id')->in($idList)->orderBy($orderBy)->fetchPairs('order', 'id');
        foreach($projects as $order => $id)
        {
            $newID = array_shift($idList);
            if($id == $newID) continue;
            $this->dao->update(TABLE_PROJECT)->set('`order`')->eq($order)->where('id')->eq($newID)->exec();
        }
    }
}
