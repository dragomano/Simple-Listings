<?php

/**
 * Class-SimpleListings.php
 *
 * @package Simple Listings
 * @link https://dragomano.ru/mods/simple-listings
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2012-2021 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.2.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class SimpleListings
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false);
		add_integration_function('integrate_actions', __CLASS__ . '::actions', false);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false);
		add_integration_function('integrate_whos_online', __CLASS__ . '::whosOnline', false);
	}

	/**
	 * Подключаем языковой файл
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		loadLanguage('SimpleListings/');
	}

	/**
	 * Добавляем пункт в главном меню
	 *
	 * @param array $buttons
	 * @return void
	 */
	public static function menuButtons(&$buttons)
	{
		global $modSettings, $txt, $scripturl;

		$counter = isset($buttons['forum']) ? 2 : 1;

		$buttons = array_merge(
			array_slice($buttons, 0, $counter, true),
			array(
				'listings' => array(
					'title' => !empty($modSettings['simple_listings_menu_item']) ? $modSettings['simple_listings_menu_item'] : $txt['simple_listings_menu'],
					'href'  => $scripturl . '?action=listings',
					'show'  => true,
					'sub_buttons' => array(
						'settings' => array(
							'title'   => $txt['settings'],
							'href'    => $scripturl . '?action=admin;area=modsettings;sa=listings',
							'show'    => allowedTo('admin_forum'),
							'is_last' => true
						)
					)
				)
			),
			array_slice($buttons, $counter, null, true)
		);
	}

	/**
	 * Вызываем определенную функцию при переходе на action=listings
	 *
	 * @param array $actionArray
	 * @return void
	 */
	public static function actions(&$actionArray)
	{
		$actionArray['listings'] = array('Class-SimpleListings.php', array(__CLASS__, 'init'));
	}

	/**
	 * Главная страница action=listings
	 *
	 * @return void
	 */
	public static function init()
	{
		global $context, $txt, $scripturl;

		loadTemplate('SimpleListings', 'simple_listings');

		$context['page_title']    = $txt['simple_listings'];
		$context['canonical_url'] = $scripturl . '?action=listings';

		$context['linktree'][] = array(
			'name' => $context['page_title'],
			'url'  => $scripturl . '?action=listings'
		);

		self::getTopicData();
	}

	/**
	 * Формируем таблицу с темами-объявлениями
	 *
	 * @return void
	 */
	private static function getTopicData()
	{
		global $modSettings, $context, $txt, $scripturl, $sourcedir;

		if (empty($modSettings['simple_listings_mode']))
			fatal_lang_error('simple_listings_offmode', false);

		$context['template_layers'][] = 'simple_listings';

		$context['can_post_new'] = false;
		$context['sel_category'] = self::getCatName();

		if (!empty($modSettings['simple_listings_category'])) {
			$modSettings['simple_listings_board'] = 0;
		}

		if (empty($modSettings['simple_listings_category']) && !empty($modSettings['simple_listings_board'])) {
			$context['can_post_new'] = allowedTo('post_new', $modSettings['simple_listings_board'])
				|| ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', $modSettings['simple_listings_board']));
		}

		$listOptions = array(
			'id' => 'sl_list',
			'items_per_page' => $modSettings['simple_listings_items_per_page'],
			'title' => '',
			'no_items_label' => $txt['simple_listings_empty'],
			'base_href' => $scripturl . '?action=listings',
			'default_sort_col' => 'date',
			'get_items' => array(
				'function' => __CLASS__ . '::getTopicEntries'
			),
			'get_count' => array(
				'function' => __CLASS__ . '::getNumTopicEntries'
			),
			'columns' => array(
				'date' => array(
					'header' => array(
						'value' => $txt['date']
					),
					'data' => array(
						'db' => 'date',
						'class' => 'centertext windowbg'
					),
					'sort' => array(
						'default' => 'm.poster_time DESC',
						'reverse' => 'm.poster_time'
					)
				),
				'last_post' => array(
					'header' => array(
						'value' => $txt['last_post']
					),
					'data' => array(
						'db' => 'last_post',
						'class' => 'centertext windowbg'
					),
					'sort' => array(
						'default' => 't.id_last_msg',
						'reverse' => 't.id_last_msg DESC'
					)
				),
				'section' => !empty($modSettings['simple_listings_category']) || empty($modSettings['simple_listings_board']) ? array(
					'header' => array(
						'value' => $txt['board']
					),
					'data' => array(
						'function' => function($entry) use ($scripturl)
						{
							return '<a href="' . $scripturl . '?board=' . $entry['board'] . '.0" target="_blank">' . $entry['name'] . '</a>';
						}
					),
					'sort' => array(
						'default' => 'b.name',
						'reverse' => 'b.name DESC'
					)
				) : null,
				'title' => array(
					'header' => array(
						'value' => $txt['topic']
					),
					'data' => array(
						'function' => function($entry) use ($scripturl, $txt)
						{
							return '<a href="' . $scripturl . '?topic=' . $entry['id'] . '.0"' . (!$entry['approved'] ? ' class="approvetbg2"' : '') . '>' . ($entry['is_sticky'] ? '<strong>' : '') . $entry['title'] . ($entry['is_sticky'] ? '</strong>' : '') . '</a>' . ($entry['is_new'] ? ' <a href="' . $entry['new_href'] . '" id="newicon' . $entry['msg'] . '" class="new_item"><strong>' . $txt['simple_listings_new'] . '</strong></a> ' : '') . (!$entry['approved'] ? '<br /><span class="smalltext"><small>' . $txt['simple_listings_not_approved'] . '</small></span>' : '');
						}
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC'
					)
				),
				'user' => array(
					'header' => array(
						'value' => $txt['author']
					),
					'data' => array(
						'function' => function($entry) use ($txt, $scripturl)
						{
							return empty($entry['poster'])
									? $txt['simple_listings_author_removed']
									: '<a href="' . $scripturl . '?action=profile;u=' . $entry['user'] . '" target="_blank">' . $entry['poster'] . '</a>';
						},
						'class' => 'centertext windowbg'
					),
					'sort' => array(
						'default' => 'poster',
						'reverse' => 'poster DESC'
					)
				),
				'replies' => array(
					'header' => array(
						'value' => $txt['replies']
					),
					'data' => array(
						'db' => 'replies',
						'class' => 'centertext windowbg'
					),
					'sort' => array(
						'default' => 't.num_replies',
						'reverse' => 't.num_replies DESC'
					)
				),
				'views' => array(
					'header' => array(
						'value' => $txt['views']
					),
					'data' => array(
						'db' => 'views',
						'class' => 'centertext windowbg'
					),
					'sort' => array(
						'default' => 't.num_views',
						'reverse' => 't.num_views DESC'
					)
				),
				'actions' => array(
					'header' => array(),
					'data' => array(
						'function' => function($entry) use ($scripturl, $context, $txt)
						{
							return (!$entry['approved'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . $entry['id'] . '.0;msg=' . $entry['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['approve'] . '"><i class="icon icon-checkbox-checked"></i></a> ' : '') . (allowedTo('admin_forum') || allowedTo('moderate_forum') ? '
							<a href="' . $scripturl . '?action=movetopic;topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['move_topic'] . '"><i class="icon icon-arrow-right"></i></a> ' : '') .
							(allowedTo('admin_forum') || allowedTo('moderate_forum') || $entry['is_own'] ? '<a href="' . $scripturl . '?action=post;msg=' . $entry['msg'] . ';topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['modify'] . '"><i class="icon icon-pencil"></i></a>
							<a href="' . $scripturl . '?action=removetopic2;topic=' . $entry['id'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['delete'] . '"><i class="icon icon-bin2"></i></a>' : '');
						},
						'class' => 'simple_listings_actions windowbg'
					)
				)
			),
			'form' => array(
				'href'          => $scripturl . '?action=listings',
				'include_sort'  => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id']
				)
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value'    => !empty($context['sel_category'])
									? $txt['simple_listings_info'] . '<br />' . sprintf($txt['simple_listings_hint'], $context['sel_category'])
									: $txt['simple_listings_info'],
					'class'    => 'smalltext',
					'style'    => 'padding: 0;'
				)
			)
		);

		// Other columns
		if (boardsAllowedTo('view_attachments'))
			$listOptions['columns'] = array_merge(
				array(
					'image' => array(
						'header' => array(
							'value' => $txt['simple_listings_image']
						),
						'data' => array(
							'function' => function($entry) use ($txt, $context)
							{
								$temp = $txt['no'];
								if (!empty($entry['thumb'])) {
									if (isset($entry['thumb']['id'])) {
										$temp ='<a id="link_' . ($entry['thumb']['id'] - 1) . '" class="highslide" onclick="if (window.hs) return hs.expand(this, { slideshowGroup: 14})" href="' . $entry['thumb']['link'] . '"><img src="' . $entry['thumb']['url'] . '" height="' . $entry['thumb']['height'] . '" alt="' . $entry['title'] . '" /></a>';
										$imagefound = true;
									} elseif (isset($entry['thumb']['url'])) {
										$temp = '<img src="' . $entry['thumb']['url'] . '" height="' . $entry['thumb']['height'] . '" alt="' . $entry['title'] . '" />';
									}
								}

								return $temp;
							},
							'class' => 'centertext windowbg'
						)
					)
				),
				$listOptions['columns']
			);

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sl_list';

		if (empty($context['sl_list']['total_num_items']))
			return;

		self::getBoardList();
	}

	/**
	 * Получаем имя категории
	 *
	 * @return null|string
	 */
	private static function getCatName()
	{
		global $modSettings, $smcFunc;

		if (empty($modSettings['simple_listings_category']))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:cat}',
			array(
				'cat' => (int) $modSettings['simple_listings_category']
			)
		);

		list ($name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return $name;
	}

	/**
	 * Формируем список разделов
	 *
	 * @return void
	 */
	private static function getBoardList()
	{
		global $modSettings, $smcFunc, $sourcedir, $context;

		if (empty($modSettings['simple_listings_category']))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_cat = {int:sel_cat}',
			array(
				'sel_cat' => (int) $modSettings['simple_listings_category']
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$included_boards[] = $row['id_board'];

		$smcFunc['db_free_result']($request);

		if (empty($included_boards))
			return;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = array(
			'included_boards' => $included_boards,
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true
		);

		$context['boards'] = getBoardList($boardListOptions);

		$context['can_post_new'] = allowedTo('post_new', $included_boards) || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', $included_boards));
	}

	/**
	 * Получаем массив тем-объявлений из указанных категории или раздела
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @return array
	 */
	public static function getTopicEntries($start, $items_per_page, $sort)
	{
		global $smcFunc, $user_info, $modSettings, $txt, $scripturl, $context;

		$request = $smcFunc['db_query']('', '
			SELECT
				m.id_msg, COALESCE(m.poster_time, 0) AS poster_time, m.id_msg_modified, m.subject, m.body, COALESCE(ml.poster_time, 0) AS last_post,
				' . ($user_info['is_guest'] ? '0' : 'COALESCE(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
				t.id_topic, t.id_first_msg, t.id_member_started AS user, t.approved, t.is_sticky, t.num_views, t.num_replies,
				b.id_board, b.name, COALESCE(mem.real_name, {string:guest}) AS poster
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($user_info['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:user})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:user})') . '
			WHERE ' . (!empty($modSettings['simple_listings_board']) ? 'b.id_board = {int:board}' : 'b.id_cat = {int:cat}') . (empty($modSettings['postmod_active']) || allowedTo('approve_posts') ? '' : '
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
			ORDER BY ' . $sort . '
			LIMIT ' . $start . ', ' . $items_per_page,
			array(
				'guest'  => $txt['guest_title'],
				'user'   => $user_info['id'],
				'cat'    => (int) $modSettings['simple_listings_category'],
				'board'  => (int) $modSettings['simple_listings_board'],
				'status' => 1
			)
		);

		$entries  = array();
		$messages = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			censorText($row['subject']);
			censorText($row['body']);

			$image = array();
			preg_match('/\[img.*](.+)\[\/img]/i', $row['body'], $img);
			if (!empty($img[1]) && empty($image)) {
				$image = array(
					'url'    => trim($img[1]),
					'height' => $modSettings['simple_listings_thumb_height']
				);
			}

			$messages[] = $row['id_msg'];
			$entries[$row['id_msg']] = array(
				'id'        => $row['id_topic'],
				'msg'       => $row['id_first_msg'],
				'date'      => timeformat($row['poster_time']),
				'last_post' => timeformat($row['last_post']),
				'board'     => $row['id_board'],
				'name'      => $row['name'],
				'title'     => $row['subject'],
				'user'      => $row['user'],
				'poster'    => $row['poster'],
				'replies'   => $row['num_replies'],
				'views'     => $row['num_views'],
				'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
				'is_new'    => $row['new_from'] <= $row['id_msg_modified'],
				'new_href'  => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'is_own'    => $row['user'] == $user_info['id'],
				'approved'  => $row['approved'],
				'thumb'     => $image
			);
		}

		$smcFunc['db_free_result']($request);

		if (!empty($messages) && !empty($modSettings['attachmentEnable']) && boardsAllowedTo('view_attachments')) {
			$request = $smcFunc['db_query']('', '
				SELECT a.id_attach, a.id_msg, t.id_topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}topics AS t ON (t.id_first_msg = a.id_msg)
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.width <> 0
					AND a.height <> 0
					AND a.approved = {int:is_approved}
					AND a.attachment_type = {int:attachment_type}',
				array(
					'message_list'    => $messages,
					'attachment_type' => 0,
					'is_approved'     => 1
				)
			);

			$attachments = array();
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$attachments[$row['id_msg']][] = array(
					'id'     => $row['id_attach'],
					'url'    => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . ($row['id_attach'] + 1) . ';image',
					'link'   => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image',
					'height' => $modSettings['simple_listings_thumb_height']
				);
			}

			$smcFunc['db_free_result']($request);

			foreach ($attachments as $id_msg => $data)
				$entries[$id_msg]['thumb'] = $data[0];
		}

		return $entries;
	}

	/**
	 * Получаем список тем-объявлений в указанных категории или разделе
	 *
	 * @return int
	 */
	public static function getNumTopicEntries()
	{
		global $smcFunc, $modSettings, $user_info;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(t.id_topic)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . (!empty($modSettings['simple_listings_board']) ? 'b.id_board = {int:board}' : 'b.id_cat = {int:cat}') . '
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')',
			array(
				'cat'    => (int) $modSettings['simple_listings_category'],
				'board'  => (int) $modSettings['simple_listings_board'],
				'status' => 1,
				'user'   => $user_info['id']
			)
		);

		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $count;
	}

	/**
	 * Указываем заголовок вкладки с настройками мода
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['listings'] = array($txt['simple_listings_settings']);
	}

	/**
	 * Подключаем функцию с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['listings'] = array(__CLASS__, 'settings');
	}

	/**
	 * Настройки мода
	 *
	 * @param bool $return_config
	 * @return void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title']     = $txt['simple_listings_settings'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=listings';

		$context[$context['admin_menu_name']]['tab_data']['tabs']['listings'] = array('description' => $txt['simple_listings_desc']);
		$txt['simple_listings_no_cat'] = sprintf($txt['simple_listings_no_cat'], $scripturl . '?action=admin;area=manageboards;sa=newcat');

		loadTemplate('SimpleListings');

		if (empty($modSettings['simple_listings_menu_item']))
			updateSettings(array('simple_listings_menu_item' => $txt['simple_listings_menu']));
		if (empty($modSettings['simple_listings_thumb_height']))
			updateSettings(array('simple_listings_thumb_height' => 80));
		if (empty($modSettings['simple_listings_items_per_page']))
			updateSettings(array('simple_listings_items_per_page' => 30));

		$categories = self::getAllCategories();
		self::prepareBoardList();

		if (empty($categories)) {
			$config_vars = array(array('desc', 'simple_listings_no_cat'));
			$context['settings_save_dont_show'] = true;
		} else {
			$categories = [0 => $txt['no']] + $categories;

			$config_vars = array(
				array('check', 'simple_listings_mode'),
				array('text', 'simple_listings_menu_item'),
				array('select', 'simple_listings_category', $categories),
				array('callback', 'select_sl_board'),
				array('int', 'simple_listings_thumb_height'),
				array('int', 'simple_listings_items_per_page')
			);
		}

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$save_vars = $config_vars;
			$save_vars[] = ['int', 'simple_listings_board'];

			saveDBSettings($save_vars);
			redirectexit('action=admin;area=modsettings;sa=listings');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * Дополнение списка «Кто онлайн» действием «Просматривает доску объявлений»
	 *
	 * @param array $actions
	 * @return string
	 */
	public static function whosOnline($actions)
	{
		global $txt, $scripturl;

		$result = '';

		if (!empty($actions['action']) && $actions['action'] == 'listings')
			$result = sprintf($txt['simple_listings_who_main'], $scripturl . '?action=listings');

		return $result;
	}

	/**
	 * Get category array
	 *
	 * Получаем массив категорий
	 *
	 * @return array
	 */
	private static function getAllCategories()
	{
		global $sourcedir;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = array(
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true
		);

		$categories = getBoardList($boardListOptions);

		return array_column($categories, 'name', 'id');
	}

	/**
	 * Формируем список разделов для установки игнорируемых
	 *
	 * @return void
	 */
	private static function prepareBoardList()
	{
		global $smcFunc, $modSettings, $context;

		$request = $smcFunc['db_query']('order_by_board_order', '
			SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE redirect = {string:empty_string}' . (!empty($modSettings['recycle_board']) ? '
				AND b.id_board != {int:recycle_board}' : ''),
			array(
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : null,
				'empty_string'  => ''
			)
		);

		$context['num_boards'] = $smcFunc['db_num_rows']($request);
		$context['categories'] = [];

		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id'     => $row['id_cat'],
					'name'   => $row['cat_name'],
					'boards' => []
				);

			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id'          => $row['id_board'],
				'name'        => $row['name'],
				'child_level' => $row['child_level'],
				'selected'    => !empty($modSettings['simple_listings_board']) && $modSettings['simple_listings_board'] == $row['id_board']
			);
		}

		$smcFunc['db_free_result']($request);
	}
}
