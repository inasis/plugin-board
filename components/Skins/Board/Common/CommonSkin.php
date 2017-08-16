<?php
namespace Xpressengine\Plugins\Board\Components\Skins\Board\Common;

use Xpressengine\Plugins\Board\GenericBoardSkin;
use View;
use XeFrontend;
use XeRegister;
use XePresenter;
use Xpressengine\Config\ConfigEntity;
use Xpressengine\Menu\Models\MenuItem;
use Xpressengine\Plugins\Board\Components\DynamicFields\Category\Skins\DesignSelect\DesignSelectSkin;
use Xpressengine\Plugins\Board\Pagination\MobilePresenter;
use Xpressengine\Plugins\Board\Pagination\BasePresenter;
use Xpressengine\Presenter\Presenter;
use Xpressengine\Routing\InstanceConfig;

class CommonSkin extends GenericBoardSkin
{
    protected static $path = 'board/components/Skins/Board/Common';

    /**
     * @var array
     */
    protected $defaultListColumns = [
        'title', 'writer', 'assentCount', 'readCount', 'createdAt', 'updatedAt', 'dissentCount',
    ];

    protected $defaultSelectedListColumns = [
        'title', 'writer',  'assentCount', 'readCount', 'createdAt',
    ];

    /**
     * @var array
     */
    protected $defaultFormColumns = [
        'title', 'content',
    ];

    /**
     * @var array
     */
    protected $defaultSelectedFormColumns = [
        'title', 'content',
    ];

    /**
     * render
     *
     * @return \Illuminate\Contracts\Support\Renderable|string
     */
    public function render()
    {
        $this->setSkinConfig();
        $this->setDynamicFieldSkins();
        $this->setPaginationPresenter();
        $this->setBoardList();
        $this->setTerms();

        // 스킨 view(blade)파일이나 js 에서 사용할 다국어 정의
        XeFrontend::translation([
            'board::selectPost',
            'board::selectBoard',
            'board::msgDeleteConfirm',
        ]);

        // set skin path
        $this->data['_skinPath'] = static::$path;

        /**
         * If view file is not 'index.blade.php' then change view path to CommonSkin's path.
         * CommonSkin extends by other Skins. Extended Skin can make just 'index.blade.php'
         * and other blade files will use to CommonSkin's blade files.
         */
        if ($this->view != 'index') {
            static::$path = self::$path;
        }
        $contentView = parent::render();

        /**
         * If render type is not for Presenter::RENDER_CONTENT
         * then use CommonSkin's '_frame.blade.php' for layout.
         * '_frame.blade.php' has assets load script like js, css.
         */
        if (XePresenter::getRenderType() == Presenter::RENDER_CONTENT) {
            $view = $contentView;
        } else {
            // wrapped by _frame.blade.php
            $view = View::make(sprintf('%s/views/_frame', CommonSkin::$path), $this->data);
            $view->content = $contentView;
        }

        return $view;
    }

    /**
     * set skin config to data
     *
     * @return void
     */
    protected function setSkinConfig()
    {
        // 기본 설정
        if (empty($this->config['listColumns'])) {
            $this->config['listColumns'] = $this->defaultSelectedListColumns;
        }
        if (empty($this->config['formColumns'])) {
            $this->config['formColumns'] = $this->defaultSelectedFormColumns;
        }
        $this->data['skinConfig'] = $this->config;
    }

    /**
     * replace dynamicField skins
     *
     * @return void
     */
    protected function setDynamicFieldSkins()
    {
        // replace dynamicField skin registered information
        XeRegister::set('fieldType/xpressengine@Category/fieldSkin/xpressengine@default', DesignSelectSkin::class);
    }

    /**
     * set pagination presenter
     * 스킨에서 추가한 만든 pagination presenter 사용
     *
     * @return void
     * @see views/defaultSkin/index.blade.php
     */
    protected function setPaginationPresenter()
    {
        if (isset($this->data['paginate'])) {
            $this->data['paginate']->setPath($this->data['urlHandler']->get('index'));
            $this->data['paginationPresenter'] = new BasePresenter($this->data['paginate']);
            $this->data['paginationMobilePresenter'] = new MobilePresenter($this->data['paginate']);
        }
    }

    /**
     * set board list (for supervisor)
     *
     * @return void
     */
    protected function setBoardList()
    {
        $instanceConfig = InstanceConfig::instance();
        $instanceId = $instanceConfig->getInstanceId();

        $configHandler = app('xe.board.config');
        $boards = $configHandler->gets();
        $boardList = [];
        /** @var ConfigEntity $config */
        foreach ($boards as $config) {
            // 현재의 게시판은 리스트에서 제외
            if ($instanceId === $config->get('boardId')) {
                continue;
            }
            $menuItem = MenuItem::find($config->get('boardId'));
            $title = xe_trans($menuItem->title);

            $boardName = $config->get('boardName');
            if ($boardName) {
                $boardName = xe_trans($boardName);
                if ($boardName != '') {
                    $title = sprintf('%s(%s)', $title, $boardName);
                }
            }

            $boardList[] = [
                'value' => $config->get('boardId'),
                'text' => $title,
            ];
        }
        $this->data['boardList'] = $boardList;
    }

    /**
     * set terms for search select box list
     *
     * @return array
     */
    protected function setTerms()
    {
        $this->data['terms'] = [
            ['value' => '1week', 'text' => 'board::1week'],
            ['value' => '2week', 'text' => 'board::2week'],
            ['value' => '1month', 'text' => 'board::1month'],
            ['value' => '3month', 'text' => 'board::3month'],
            ['value' => '6month', 'text' => 'board::6month'],
            ['value' => '1year', 'text' => 'board::1year'],
        ];
    }

    /**
     * get setting view
     *
     * @param array $config board config
     * @return \Illuminate\Contracts\Support\Renderable|string
     */
    public function renderSetting(array $config = [])
    {
        if ($config === []) {
            $config = [
                'listColumns' => $this->defaultSelectedListColumns,
                'formColumns' => $this->defaultSelectedFormColumns,
            ];
        }

        $arr = explode(':', request()->get('instanceId'));
        $instanceId = $arr[1];

        return View::make(
            sprintf('%s/views/setting', CommonSkin::$path),
            [
                'sortListColumns' => $this->getSortListColumns($config, $instanceId),
                'sortFormColumns' => $this->getSortFormColumns($config, $instanceId),
                'config' => $config
            ]
        );
    }

    /**
     * get sort list columns
     *
     * @param array  $config     board config
     * @param string $instanceId board instance id
     * @return array
     */
    protected function getSortListColumns(array $config, $instanceId)
    {
        /** @var \Xpressengine\Plugins\Board\ConfigHandler $configHandler */
        $configHandler = app('xe.board.config');

        if (empty($config['sortListColumns'])) {
            $sortListColumns = $this->defaultListColumns;
        } else {
            $sortListColumns = $config['sortListColumns'];
        }

        $dynamicFields = $configHandler->getDynamicFields($configHandler->get($instanceId));
        $currentDynamicFields = [];
        /**
         * @var ConfigEntity $dynamicFieldConfig
         */
        foreach ($dynamicFields as $dynamicFieldConfig) {
            if ($dynamicFieldConfig->get('use') === true) {
                $currentDynamicFields[] = $dynamicFieldConfig->get('id');
            }

            if ($dynamicFieldConfig->get('use') === true &&
                in_array($dynamicFieldConfig->get('id'), $sortListColumns) === false) {
                $sortListColumns[] = $dynamicFieldConfig->get('id');
            }
        }

        $usableColumns = array_merge($this->defaultListColumns, $currentDynamicFields);
        foreach ($sortListColumns as $index => $column) {
            if (in_array($column, $usableColumns) === false) {
                unset($sortListColumns[$index]);
            }
        }

        return $sortListColumns;
    }

    /**
     * get sort form columns
     *
     * @param array  $config     board config
     * @param string $instanceId board instance id
     * @return array
     */
    protected function getSortFormColumns(array $config, $instanceId)
    {
        /** @var \Xpressengine\Plugins\Board\ConfigHandler $configHandler */
        $configHandler = app('xe.board.config');

        if (empty($config['sortFormColumns'])) {
            $sortFormColumns = $this->defaultFormColumns;
        } else {
            $sortFormColumns = $config['sortFormColumns'];
        }
        $dynamicFields = $configHandler->getDynamicFields($configHandler->get($instanceId));
        $currentDynamicFields = [];
        /**
         * @var ConfigEntity $dynamicFieldConfig
         */
        foreach ($dynamicFields as $dynamicFieldConfig) {
            if ($dynamicFieldConfig->get('use') === true) {
                $currentDynamicFields[] = $dynamicFieldConfig->get('id');
            }

            if ($dynamicFieldConfig->get('use') === true &&
                in_array($dynamicFieldConfig->get('id'), $sortFormColumns) === false) {
                $sortFormColumns[] = $dynamicFieldConfig->get('id');
            }
        }

        $usableColumns = array_merge($this->defaultFormColumns, $currentDynamicFields);
        foreach ($sortFormColumns as $index => $column) {
            if (in_array($column, $usableColumns) === false) {
                unset($sortFormColumns[$index]);
            }
        }

        return $sortFormColumns;
    }
}