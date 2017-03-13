<?php
/**
 * BoardModule
 *
 * PHP version 5
 *
 * @category    Board
 * @package     Xpressengine\Plugins\Board
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */
namespace Xpressengine\Plugins\Board\Modules;

use Route;
use XeSkin;
use View;
use Mail;
use Xpressengine\Menu\AbstractModule;
use Xpressengine\Plugins\Board\Handler as BoardHandler;
use Xpressengine\Plugins\Board\ConfigHandler;
use Xpressengine\Plugins\Board\UrlHandler;
use Xpressengine\Plugins\Board\Models\Board as BoardModel;
use Xpressengine\Plugins\Board\Models\BoardSlug;
use Xpressengine\Plugins\Comment\Handler as CommentHandler;
use Xpressengine\Plugins\Comment\Models\Comment;
use Xpressengine\Plugins\Comment\Models\Target as CommentTarget;

/**
 * BoardModule
 *
 * AbstractModule 인터페이스 지원. 메뉴로 추가할 수 있음.
 *
 * @category    Board
 * @package     Xpressengine\Plugins\Board
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */
class BoardModule extends AbstractModule
{
    const FILE_UPLOAD_PATH = 'public/plugin/board';
    const THUMBNAIL_TYPE = 'spill';

    /**
     * boot
     *
     * @return void
     */
    public static function boot()
    {
        self::registerArchiveRoute();
        self::registerManageRoute();
        self::registerInstanceRoute();
        self::registerSettingsMenu();
        self::registerCommentCountIntercept();
        self::registerCommentAlarmIntercept();
        self::registerManagerAlarmIntercept();
    }

    /**
     * register plugin archive route
     *
     * @return void
     */
    protected static function registerArchiveRoute()
    {
        // set routing
        config(['xe.routing' => array_merge(
            config('xe.routing'),
            ['board_archives' => 'archives']
        )]);

        Route::group([
            'prefix' => 'archives',
            'namespace' => 'Xpressengine\Plugins\Board\Controllers'
        ], function () {
            Route::get('/{slug}', ['as' => 'archives', 'uses' => 'ArchivesController@index']);
        });
    }

    /**
     * Register Plugin Manage Route
     *
     * @return void
     */
    protected static function registerManageRoute()
    {
        Route::settings(self::getId(), function () {
            Route::get('/', ['as' => 'manage.board.board.index', 'uses' => 'BoardSettingsController@index']);
            Route::get(
                '/global/edit',
                ['as' => 'manage.board.board.global.edit', 'uses' => 'BoardSettingsController@globalEdit']
            );
            Route::post(
                '/global/update',
                ['as' => 'manage.board.board.global.update', 'uses' => 'BoardSettingsController@globalUpdate']
            );
            Route::get('edit/{boardId}', ['as' => 'manage.board.board.edit', 'uses' => 'BoardSettingsController@edit']);
            Route::post('storeCategory/', [
                'as' => 'manage.board.board.storeCategory', 'uses' => 'BoardSettingsController@storeCategory'
            ]);
            Route::post(
                'update/{boardId}',
                ['as' => 'manage.board.board.update', 'uses' => 'BoardSettingsController@update']
            );
            Route::get('docs', [
                'as' => 'manage.board.board.docs.index',
                'uses' => 'BoardSettingsController@docsIndex',
                'settings_menu' => 'contents.board.board'
            ]);
            Route::get('docs/trash', [
                'as' => 'manage.board.board.docs.trash',
                'uses' => 'BoardSettingsController@docsTrash',
                'settings_menu' => 'contents.board.boardtrash'
            ]);
            Route::get('docs/approve', [
                'as' => 'manage.board.board.docs.approve',
                'uses' => 'BoardSettingsController@docsApprove',
                'settings_menu' => 'contents.board.boardapprove'
            ]);
            Route::post('approve', ['as' => 'manage.board.board.approve', 'uses' => 'BoardSettingsController@approve']);
            Route::post('copy', ['as' => 'manage.board.board.copy', 'uses' => 'BoardSettingsController@copy']);
            Route::post('destroy', ['as' => 'manage.board.board.destroy', 'uses' => 'BoardSettingsController@destroy']);
            Route::post('trash', ['as' => 'manage.board.board.trash', 'uses' => 'BoardSettingsController@trash']);
            Route::post('move', ['as' => 'manage.board.board.move', 'uses' => 'BoardSettingsController@move']);
            Route::post('restore', ['as' => 'manage.board.board.restore', 'uses' => 'BoardSettingsController@restore']);
        }, ['namespace' => 'Xpressengine\Plugins\Board\Controllers']);
    }

    /**
     * Register Plugin Instance Route
     *
     * @return void
     */
    protected static function registerInstanceRoute()
    {
        Route::instance(self::getId(), function () {
            Route::get('/', ['as' => 'index', 'uses' => 'BoardModuleController@index']);
            Route::get('/show/{id}', ['as' => 'show', 'uses' => 'BoardModuleController@show']);

            Route::get('/articles', ['as' => 'api.articles', 'uses' => 'BoardModuleController@articles']);
            Route::get('/notices', ['as' => 'api.notices', 'uses' => 'BoardModuleController@notices']);
            Route::get('/articles/{id}', ['as' => 'api.article', 'uses' => 'BoardModuleController@get']);

            Route::get('/create', ['as' => 'create', 'uses' => 'BoardModuleController@create']);
            Route::post('/store', ['as' => 'store', 'uses' => 'BoardModuleController@store']);

            Route::get('/edit/{id}', ['as' => 'edit', 'uses' => 'BoardModuleController@edit']);
            Route::post('/update', ['as' => 'update', 'uses' => 'BoardModuleController@update']);

            Route::delete('/destroy/{id}', ['as' => 'destroy', 'uses' => 'BoardModuleController@destroy']);

            Route::get('/guest/id/{id}', ['as' => 'guest.id', 'uses' => 'BoardModuleController@guestId']);
            Route::post('/guest/certify/{id}', [
                'as' => 'guest.certify', 'uses' => 'BoardModuleController@guestCertify'
            ]);

            Route::get('/revision/{id}', ['as' => 'revision', 'uses' => 'BoardModuleController@revision']);

            Route::post('/preview', ['as' => 'preview', 'uses' => 'BoardModuleController@preview']);
            Route::post('/temporary', ['as' => 'temporary', 'uses' => 'BoardModuleController@temporary']);
            Route::post('/trash', ['as' => 'trash', 'uses' => 'BoardModuleController@trash']);

            Route::post('/vote/{option}/{id}', ['as' => 'vote', 'uses' => 'BoardModuleController@vote']);
            Route::get('/vote/show/{id}', ['as' => 'showVote', 'uses' => 'BoardModuleController@showVote']);
            Route::get('/vote/users/{option}/{id}', [
                'as' => 'votedUsers', 'uses' => 'BoardModuleController@votedUsers'
            ]);
            Route::get('/vote/modal/{option}/{id}', [
                'as' => 'votedModal', 'uses' => 'BoardModuleController@votedModal'
            ]);
            Route::get('/vote/userList/{option}/{id}', [
                'as' => 'votedUserList', 'uses' => 'BoardModuleController@votedUserList'
            ]);

            Route::post('/favorite/{id}', ['as' => 'favorite', 'uses' => 'BoardModuleController@favorite']);

            Route::get('/hasSlug', ['as' => 'hasSlug', 'uses' => 'BoardModuleController@hasSlug']);
            Route::get('/{slug}', ['as' => 'slug', 'uses' => 'BoardModuleController@slug']);
        }, ['namespace' => 'Xpressengine\Plugins\Board\Controllers']);

        BoardSlug::setReserved([
            'index', 'create', 'edit', 'destroy', 'show', 'identify', 'revision', 'store', 'preview', 'temporary',
            'trash', 'certify', 'update', 'vote', 'manageMenus', 'comment', 'file', 'suggestion', 'slug', 'hasSlug',
            'favorite'
        ]);
    }

    /**
     * register interception
     *
     * @return void
     */
    public static function registerSettingsMenu()
    {
        // settings menu 등록
        $menus = [
            'contents.board' => [
                'title' => 'board::board',
                'display' => true,
                'description' => '',
                'ordering' => 2000
            ],
            'contents.board.board' => [
                'title' => 'board::articlesManage',
                'display' => true,
                'description' => '',
                'ordering' => 2001
            ],
            'contents.board.boardapprove' => [
                'title' => 'board::articlesApprove',
                'display' => true,
                'description' => '',
                'ordering' => 2002
            ],
            'contents.board.boardtrash' => [
                'title' => 'board::trashManage',
                'display' => true,
                'description' => '',
                'ordering' => 2003
            ],
        ];
        foreach ($menus as $id => $menu) {
            app('xe.register')->push('settings/menu', $id, $menu);
        }
    }

    /**
     * register intercept for comment count
     *
     * @return void
     */
    public static function registerCommentCountIntercept()
    {
        intercept(
            sprintf('%s@create', CommentHandler::class),
            static::class.'-comment-create',
            function ($func, array $inputs, $user = null) {
                $comment = $func($inputs, $user);

                $board = BoardModel::find($comment->target->targetId);

                if ($board == null) {
                    return $comment;
                }
                if ($board->type != static::getId()) {
                    return $comment;
                }

                /** @var BoardHandler $handler */
                $handler = app('xe.board.handler');
                /** @var ConfigHandler $configHandler */
                $configHandler = app('xe.board.config');

                $handler->setModelConfig($board, $configHandler->get($board->instanceId));
                $board->commentCount = CommentTarget::where('targetId', $board->id)->count();
                $board->save();

                return $comment;
            }
        );

        intercept(
            sprintf('%s@trash', CommentHandler::class),
            static::class.'-comment-trash',
            function ($func, Comment $comment) {
                $result = $func($comment);

                if ($board = BoardModel::find($comment->target->targetId)) {
                    if ($board == null) {
                        return $result;
                    }
                    if ($board->type != static::getId()) {
                        return $result;
                    }

                    /** @var BoardHandler $handler */
                    $handler = app('xe.board.handler');
                    /** @var ConfigHandler $configHandler */
                    $configHandler = app('xe.board.config');

                    $handler->setModelConfig($board, $configHandler->get($board->instanceId));
                    $board->commentCount = CommentTarget::where('targetId', $board->id)->count();
                    $board->save();
                }

                return $result;
            }
        );

        intercept(
            sprintf('%s@remove', CommentHandler::class),
            static::class.'-comment-remove',
            function ($func, Comment $comment) {
                $result = $func($comment);

                if ($board = BoardModel::find($comment->target->targetId)) {
                    if ($board == null) {
                        return $result;
                    }
                    if ($board->type != static::getId()) {
                        return $result;
                    }

                    /** @var BoardHandler $handler */
                    $handler = app('xe.board.handler');
                    /** @var ConfigHandler $configHandler */
                    $configHandler = app('xe.board.config');

                    $handler->setModelConfig($board, $configHandler->get($board->instanceId));
                    $board->commentCount = CommentTarget::where('targetId', $board->id)->count();
                    $board->save();
                }

                return $result;
            }
        );

        intercept(
            sprintf('%s@restore', CommentHandler::class),
            static::class.'-comment-restore',
            function ($func, Comment $comment) {
                $result = $func($comment);

                if ($board = BoardModel::find($comment->target->targetId)) {
                    if ($board == null) {
                        return $result;
                    }
                    if ($board->type != static::getId()) {
                        return $result;
                    }

                    /** @var BoardHandler $handler */
                    $handler = app('xe.board.handler');
                    /** @var ConfigHandler $configHandler */
                    $configHandler = app('xe.board.config');

                    $handler->setModelConfig($board, $configHandler->get($board->instanceId));
                    $board->commentCount = CommentTarget::where('targetId', $board->id)->count();
                    $board->save();
                }

                return $result;
            }
        );
    }

    /**
     * register intercept ofr comment alarm
     *
     * @return void
     */
    public static function registerCommentAlarmIntercept()
    {
        intercept(
            sprintf('%s@create', CommentHandler::class),
            static::class.'-comment-alarm',
            function ($func, $inputs, $user = null) {
                $comment = $func($inputs, $user);

                $board = BoardModel::find($comment->target->targetId);

                if ($board == null) {
                    return $comment;
                }
                if ($board->type != static::getId()) {
                    return $comment;
                }
                if ($board->userId == $comment->userId) {
                    return $comment;
                }
                if ($board->userId == '') {
                    return $comment;
                }
                if ($board->boardData->isAlarm() === false) {
                    return $comment;
                }

                /** @var UrlHandler $urlHandler */
                $urlHandler = app('xe.board.url');
                $urlHandler->setConfig(app('xe.board.config')->get($board->instanceId));
                $url = $urlHandler->getShow($board);
                $data = [
                    'title' => xe_trans('board::newCommentRegistered'),
                    'contents' => sprintf(
                        '<a href="%s" target="_blank">%s</a><br/><br/><br/>%s',
                        $url,
                        $url,
                        xe_trans(
                            'board::newCommentRegisteredBy',
                            ['displayName' => $comment->author->getDisplayName()]
                        )
                    ),
                ];

                Mail::send('emails.notice', $data, function ($m) use ($board) {
                    $writer = $board->user;
                    if ($writer->email != '') {
                        $fromEmail = app('config')->get('mail.from.address');
                        $applicationName = xe_trans(app('xe.site')->getSiteConfig()->get('site_title'));

                        $menuItem = app('xe.menu')->getItem($board->instanceId);
                        $subject = sprintf('Re:[%s] %s', xe_trans($menuItem->title), $board->title);

                        $m->from($fromEmail, $applicationName);
                        $m->to($writer->email, $writer->getDisplayName());
                        $m->subject($subject);
                    }
                });

                return $comment;
            }
        );
    }

    /**
     * register intercept for manager alarm
     *
     * @return void
     */
    public static function registerManagerAlarmIntercept()
    {
        intercept(
            sprintf('%s@add', BoardHandler::class),
            static::class .'-manager-board-alarm',
            function ($func, $args, $user, $config) {
                $board = $func($args, $user, $config);

                /** @var ConfigHandler $configHandler */
                $configHandler = app('xe.board.config');
                $config = $configHandler->get($board->instanceId);
                if ($config->get('managerEmail', '') === '') {
                    return $board;
                }

                $managerEmails = explode(',', trim($config->get('managerEmail')));
                if (count($managerEmails) == 0) {
                    return $board;
                }

                /** @var UrlHandler $urlHandler */
                $urlHandler = app('xe.board.url');
                $urlHandler->setConfig($config);
                $url = $urlHandler->getShow($board);

                $data = [
                    'title' => xe_trans('board::newPostsRegistered'),
                    'contents' => sprintf(
                        '<a href="%s" target="_blank">%s</a><br/><br/><br/>%s',
                        $url,
                        $url,
                        $board->pureContent
                    ),
                ];

                foreach ($managerEmails as $toMail) {
                    if (!$toMail) {
                        continue;
                    }
                    Mail::send('emails.notice', $data, function ($m) use ($toMail, $board) {
                        $fromEmail = app('config')->get('mail.from.address');
                        $applicationName = xe_trans(app('xe.site')->getSiteConfig()->get('site_title'));

                        $menuItem = app('xe.menu')->getItem($board->instanceId);
                        $subject = sprintf(
                            '[%s - %s] %s',
                            $applicationName,
                            xe_trans($menuItem->title),
                            xe_trans('board::newPostsRegistered')
                        );

                        $m->from($fromEmail, $applicationName);
                        $m->to($toMail, 'Board manager');
                        $m->subject($subject);
                    });

                }

                return $board;
            }
        );
    }

    /**
     * get manage URI
     *
     * @return string
     */
    public static function getSettingsURI()
    {
        return route('manage.board.board.global.edit');
    }

    /**
     * this module is route able
     *
     * @return bool
     */
    public static function isRouteAble()
    {
        return true;
    }

    /**
     * Return Create Form View
     *
     * @return string
     */
    public function createMenuForm()
    {
        $skins = XeSkin::getList('module/board@board');

        return View::make('board::views/menuType/create', [
            'boardId' => null,
            'config' => app('xe.board.config')->getDefault(),
            'skins' => $skins,
            'handler' => app('xe.board.handler'),
        ])->render();
    }

    /**
     * Process to Store
     *
     * @param string $instanceId     instance id
     * @param array  $menuTypeParams menu type parameters
     * @param array  $itemParams     item parameters
     * @return void
     */
    public function storeMenu($instanceId, $menuTypeParams, $itemParams)
    {
        $input = $menuTypeParams;
        $input['boardId'] = $instanceId;

        app('xe.board.instance')->create($input);
        app('xe.editor')->setInstance($instanceId, 'editor/ckeditor@ckEditor');
    }

    /**
     * Return Edit Form View
     *
     * @param string $instanceId instance id
     * @return string
     */
    public function editMenuForm($instanceId)
    {
        $skins = XeSkin::getList(self::getId());

        return View::make('board::views/menuType/edit', [
            'boardId' => $instanceId,
            'config' => app('xe.board.config')->get($instanceId),
            'skins' => $skins,
            'handler' => app('xe.board.handler'),
        ])->render();
    }

    /**
     * Process to Update
     *
     * @param string $instanceId     instance id
     * @param array  $menuTypeParams menu type parameters
     * @param array  $itemParams     item parameters
     * @return void
     */
    public function updateMenu($instanceId, $menuTypeParams, $itemParams)
    {
        $menuTypeParams['boardId'] = $instanceId;

        app('xe.board.instance')->updateConfig($menuTypeParams);
    }

    /**
     * Process to delete
     *
     * @param string $instanceId instance id
     * @return void
     */
    public function deleteMenu($instanceId)
    {
        app('xe.board.instance')->destroy($instanceId);
    }

    /**
     * summary
     *
     * @param string $instanceId instance id
     * @return string
     */
    public function summary($instanceId)
    {
        return xe_trans(
            'board::destroySummary',
            app('xe.board.instance')->summary($instanceId, app('xe.board.handler'))
        );
    }

    /**
     * Return URL about module's detail setting
     * getInstanceSettingURI
     *
     * @param string $instanceId instance id
     * @return mixed
     */
    public static function getInstanceSettingURI($instanceId)
    {
        return route('manage.board.board.edit', $instanceId);
    }

    /**
     * Get menu type's item object
     *
     * @param string $id item id of menu type
     * @return mixed
     */
    public function getTypeItem($id)
    {
        static $items = [];

        if (!isset($items[$id])) {
            $items[$id] = \Xpressengine\Plugins\Board\Models\Board::find($id);
        }

        return $items[$id];
    }
}
