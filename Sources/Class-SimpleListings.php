<?php

/**
 * Class-SimpleListings.php
 *
 * @package Simple Listings
 * @link https://dragomano.ru/mods/simple-listings
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2012-2025 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.3.3
 */

if (! defined('SMF'))
	die('No direct access...');

final class SimpleListings
{
	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons#', false, __FILE__);
		add_integration_function('integrate_actions', __CLASS__ . '::actions#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
		add_integration_function('integrate_whos_online', __CLASS__ . '::whosOnline#', false, __FILE__);
	}

	public function loadTheme(): void
	{
		loadLanguage('SimpleListings/');
	}

	public function menuButtons(array &$buttons): void
	{
		global $modSettings, $txt, $scripturl;

		if (! isset($txt['simple_listings_menu']) || empty($modSettings['simple_listings_mode']))
			return;

		$counter = isset($buttons['forum']) ? 2 : 1;

		$buttons = array_merge(
			array_slice($buttons, 0, $counter, true),
			[
				'listings' => [
					'title' => empty($modSettings['simple_listings_menu_item']) ? $txt['simple_listings_menu'] : $modSettings['simple_listings_menu_item'],
					'href'  => $scripturl . '?action=listings',
					'icon'  => 'logs',
					'show'  => true,
					'sub_buttons' => [
						'settings' => [
							'title'   => $txt['settings'],
							'href'    => $scripturl . '?action=admin;area=modsettings;sa=listings',
							'show'    => allowedTo('admin_forum'),
							'is_last' => true
						]
					]
				]
			],
			array_slice($buttons, $counter, null, true)
		);
	}

	public function actions(array &$actionArray): void
	{
		$actionArray['listings'] = [false, [$this, 'init']];
	}

	public function init(): void
	{
		global $context, $txt, $scripturl;

		loadTemplate('SimpleListings', 'simple_listings');

		$context['page_title']    = $txt['simple_listings'];
		$context['canonical_url'] = $scripturl . '?action=listings';

		$context['linktree'][] = [
			'name' => $context['page_title'],
			'url'  => $scripturl . '?action=listings'
		];

		$this->getTopicData();
	}

	private function getTopicData(): void
	{
		global $modSettings, $context, $txt, $scripturl, $sourcedir;

		if (empty($modSettings['simple_listings_mode'])) {
			fatal_lang_error('simple_listings_offmode', false);
		}

		$context['template_layers'][] = 'simple_listings';

		$context['can_post_new'] = false;
		$context['sel_category'] = $this->getCatName();

		if (! empty($modSettings['simple_listings_category'])) {
			$modSettings['simple_listings_board'] = 0;
		}

		if (empty($modSettings['simple_listings_category']) && ! empty($modSettings['simple_listings_board'])) {
			$context['can_post_new'] = allowedTo('post_new', $modSettings['simple_listings_board'])
				|| ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', $modSettings['simple_listings_board']));
		}

		$this->prepareColumns();

		$columns = [];

		if (! empty($context['simple_listings_displayed_columns'][1]['show']) && boardsAllowedTo('view_attachments'))
			$columns['image'] = [
				'header' => [
					'value' => $txt['simple_listings_image']
				],
				'data' => [
					'function' => function ($entry) use ($txt, $context) {
						$temp = $txt['no'];
						if (! empty($entry['thumb'])) {
							if (isset($entry['thumb']['id'])) {
								$temp ='<a id="link_' . ($entry['thumb']['id'] - 1) . '" data-fancybox="simple_listings" href="' . $entry['thumb']['link'] . '"><img src="' . $entry['thumb']['url'] . '" height="' . $entry['thumb']['height'] . '" alt="' . $entry['title'] . '"></a>';
							} elseif (isset($entry['thumb']['url'])) {
								$temp = '<img src="' . $entry['thumb']['url'] . '" height="' . $entry['thumb']['height'] . '" alt="' . $entry['title'] . '">';
							}
						}

						return $temp;
					},
					'class' => 'centertext'
				]
			];

		$columns['date'] = [
			'header' => [
				'value' => $txt['date']
			],
			'data' => [
				'db' => 'date',
				'class' => 'centertext'
			],
			'sort' => [
				'default' => 'm.poster_time DESC',
				'reverse' => 'm.poster_time'
			]
		];

		if (! empty($context['simple_listings_displayed_columns'][3]['show']))
			$columns['last_post'] = [
				'header' => [
					'value' => $txt['last_post']
				],
				'data' => [
					'db'    => 'last_post',
					'class' => 'centertext'
				],
				'sort' => [
					'default' => 't.id_last_msg',
					'reverse' => 't.id_last_msg DESC'
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][4]['show']) && (! empty($modSettings['simple_listings_category']) || empty($modSettings['simple_listings_board'])))
			$columns['section'] = [
				'header' => [
					'value' => $txt['board']
				],
				'data' => [
					'function' => function ($entry) use ($scripturl) {
						return '<a href="' . $scripturl . '?board=' . $entry['board'] . '.0" target="_blank">' . $entry['name'] . '</a>';
					}
				],
				'sort' => [
					'default' => 'b.name',
					'reverse' => 'b.name DESC'
				]
			];

		$columns['title'] = [
			'header' => [
				'value' => $txt['topic']
			],
			'data' => [
				'function' => function ($entry) use ($scripturl, $txt) {
					return ($entry['is_new'] ? ' <a href="' . $entry['new_href'] . '" id="newicon' . $entry['msg'] . '" class="new_posts">' . $txt['simple_listings_new'] . '</a> ' : '') . '<a href="' . $scripturl . '?topic=' . $entry['id'] . '.0"' . (!$entry['approved'] ? ' class="error"' : '') . '>' . ($entry['is_sticky'] ? '<strong>' : '') . $entry['title'] . ($entry['is_sticky'] ? '</strong>' : '') . '</a>' . (!$entry['approved'] ? '<br><span class="smalltext">' . $txt['simple_listings_not_approved'] . '</span>' : '');
				}
			],
			'sort' => [
				'default' => 'm.subject',
				'reverse' => 'm.subject DESC'
			]
		];

		if (! empty($context['simple_listings_displayed_columns'][6]['show']))
			$columns['user'] = [
				'header' => [
					'value' => $txt['author']
				],
				'data' => [
					'function' => function ($entry) use ($txt, $scripturl) {
						return empty($entry['poster'])
							? $txt['simple_listings_author_removed']
							: '<a href="' . $scripturl . '?action=profile;u=' . $entry['user'] . '" target="_blank">' . $entry['poster'] . '</a>';
					},
					'class' => 'centertext'
				],
				'sort' => [
					'default' => 'poster',
					'reverse' => 'poster DESC'
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][7]['show']))
			$columns['replies'] = [
				'header' => [
					'value' => $txt['replies']
				],
				'data' => [
					'db'    => 'replies',
					'class' => 'centertext'
				],
				'sort' => [
					'default' => 't.num_replies',
					'reverse' => 't.num_replies DESC'
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][8]['show']))
			$columns['views'] = [
				'header' => [
					'value' => $txt['views']
				],
				'data' => [
					'db'    => 'views',
					'class' => 'centertext'
				],
				'sort' => [
					'default' => 't.num_views',
					'reverse' => 't.num_views DESC'
				]
			];

		$listOptions = [
			'id' => 'sl_list',
			'items_per_page' => $modSettings['simple_listings_items_per_page'],
			'title' => '',
			'no_items_label' => $txt['simple_listings_empty'],
			'base_href' => $scripturl . '?action=listings',
			'default_sort_col' => 'date',
			'get_items' => [
				'function' => __CLASS__ . '::getTopicEntries#'
			],
			'get_count' => [
				'function' => __CLASS__ . '::getNumTopicEntries#'
			],
			'columns' => array_merge(
				$columns,
				[
					'actions' => [
						'header' => [],
						'data' => [
							'function' => function ($entry) use ($scripturl, $context, $txt) {
								return ($entry['approved'] ? '' : '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . $entry['id'] . '.0;msg=' . $entry['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['approve'] . '"><i class="icon icon-checkbox-checked"></i></a> ') . (allowedTo('admin_forum') || allowedTo('moderate_forum') ? '
								<a href="' . $scripturl . '?action=movetopic;topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['move_topic'] . '"><i class="icon icon-arrow-right"></i></a> ' : '') .
								(allowedTo('admin_forum') || allowedTo('moderate_forum') || $entry['is_own'] ? '<a href="' . $scripturl . '?action=post;msg=' . $entry['msg'] . ';topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['modify'] . '"><i class="icon icon-pencil"></i></a>
								<a href="' . $scripturl . '?action=removetopic2;topic=' . $entry['id'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['delete'] . '"><i class="icon icon-bin2"></i></a>' : '');
							},
							'class' => 'simple_listings_actions'
						]
					]
				]
			),
			'form' => [
				'href'          => $scripturl . '?action=listings',
				'include_sort'  => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id']
				]
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value'    => ! empty($context['sel_category'])
						? $txt['simple_listings_info'] . '<br>' . sprintf($txt['simple_listings_hint'], $context['sel_category'])
						: $txt['simple_listings_info'],
					'class'    => 'smalltext',
					'style'    => 'padding: 0;'
				]
			]
		];

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sl_list';

		if (empty($context['sl_list']['total_num_items']))
			return;

		$this->getBoardList();
	}

	private function getCatName(): string
	{
		global $modSettings, $smcFunc;

		if (empty($modSettings['simple_listings_category']))
			return '';

		$request = $smcFunc['db_query']('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:cat}',
			[
				'cat' => (int) $modSettings['simple_listings_category']
			]
		);

		list ($name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (string) $name;
	}

	private function getBoardList(): void
	{
		global $modSettings, $smcFunc, $sourcedir, $context;

		if (empty($modSettings['simple_listings_category']))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_cat = {int:sel_cat}',
			[
				'sel_cat' => (int) $modSettings['simple_listings_category']
			]
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$included_boards[] = $row['id_board'];

		$smcFunc['db_free_result']($request);

		if (empty($included_boards))
			return;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = [
			'included_boards' => $included_boards,
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true
		];

		$context['boards'] = getBoardList($boardListOptions);

		$context['can_post_new'] = allowedTo('post_new', $included_boards) || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', $included_boards));
	}

	public function getTopicEntries(int $start, int $items_per_page, string $sort): array
	{
		global $smcFunc, $user_info, $modSettings, $txt, $scripturl;

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
			WHERE ' . (! empty($modSettings['simple_listings_board']) ? 'b.id_board = {int:board}' : 'b.id_cat = {int:cat}') . (empty($modSettings['postmod_active']) || allowedTo('approve_posts') ? '' : '
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
			ORDER BY ' . $sort . '
			LIMIT ' . $start . ', ' . $items_per_page,
			[
				'guest'  => $txt['guest_title'],
				'user'   => $user_info['id'],
				'cat'    => (int) ($modSettings['simple_listings_category'] ?? 0),
				'board'  => (int) ($modSettings['simple_listings_board'] ?? 0),
				'status' => 1
			]
		);

		$entries  = [];
		$messages = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			censorText($row['subject']);
			censorText($row['body']);

			$image = [];
			preg_match('/\[img.*](.+)\[\/img]/i', $row['body'], $img);
			if (! empty($img[1]) && empty($image)) {
				$image = [
					'url'    => trim($img[1]),
					'height' => $modSettings['simple_listings_thumb_height']
				];
			}

			$messages[] = $row['id_msg'];
			$entries[$row['id_msg']] = [
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
				'is_sticky' => ! empty($modSettings['enableStickyTopics']) && ! empty($row['is_sticky']),
				'is_new'    => $row['new_from'] <= $row['id_msg_modified'],
				'new_href'  => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'is_own'    => $row['user'] == $user_info['id'],
				'approved'  => $row['approved'],
				'thumb'     => $image
			];
		}

		$smcFunc['db_free_result']($request);

		if (! empty($messages) && ! empty($modSettings['attachmentEnable']) && boardsAllowedTo('view_attachments')) {
			$request = $smcFunc['db_query']('', '
				SELECT a.id_attach, a.id_msg, t.id_topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}topics AS t ON (t.id_first_msg = a.id_msg)
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.width <> 0
					AND a.height <> 0
					AND a.approved = {int:is_approved}
					AND a.attachment_type = {int:attachment_type}',
				[
					'message_list'    => $messages,
					'attachment_type' => 0,
					'is_approved'     => 1
				]
			);

			$attachments = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$attachments[$row['id_msg']][] = [
					'id'     => $row['id_attach'],
					'url'    => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . ($row['id_attach'] + 1) . ';image',
					'link'   => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image',
					'height' => $modSettings['simple_listings_thumb_height']
				];
			}

			$smcFunc['db_free_result']($request);

			foreach ($attachments as $id_msg => $data)
				$entries[$id_msg]['thumb'] = $data[0];
		}

		return $entries;
	}

	public function getNumTopicEntries(): int
	{
		global $smcFunc, $modSettings, $user_info;

		$request = $smcFunc['db_query']('', /** @lang text */ '
			SELECT COUNT(t.id_topic)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . (! empty($modSettings['simple_listings_board']) ? 'b.id_board = {int:board}' : 'b.id_cat = {int:cat}') . '
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')',
			[
				'cat'    => (int) ($modSettings['simple_listings_category'] ?? 0),
				'board'  => (int) ($modSettings['simple_listings_board'] ?? 0),
				'status' => 1,
				'user'   => $user_info['id']
			]
		);

		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $count;
	}

	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['listings'] = [$txt['simple_listings_settings']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search): void
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=listings'];
	}

	public function modifyModifications(array &$subActions): void
	{
		$subActions['listings'] = [$this, 'settings'];
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title']     = $txt['simple_listings_settings'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=listings';

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['simple_listings_desc'];
		$txt['simple_listings_no_cat'] = sprintf($txt['simple_listings_no_cat'], $scripturl . '?action=admin;area=manageboards;sa=newcat');

		$addSettings = [];
		if (empty($modSettings['simple_listings_menu_item']))
			$addSettings['simple_listings_menu_item'] = $txt['simple_listings_menu'];
		if (empty($modSettings['simple_listings_thumb_height']))
			$addSettings['simple_listings_thumb_height'] = 80;
		if (empty($modSettings['simple_listings_items_per_page']))
			$addSettings['simple_listings_items_per_page'] = 30;
		if ($addSettings)
			updateSettings($addSettings);

		if (empty($categories = $this->getAllCategories())) {
			$config_vars = [['desc', 'simple_listings_no_cat']];
			$context['settings_save_dont_show'] = true;
		} else {
			loadTemplate('SimpleListings');

			$categories = [0 => $txt['no']] + $categories;

			$this->prepareBoardList();
			$this->prepareColumns();

			$config_vars = [
				['check', 'simple_listings_mode'],
				['text', 'simple_listings_menu_item'],
				['select', 'simple_listings_category', $categories],
				['callback', 'select_sl_board'],
				['int', 'simple_listings_thumb_height'],
				['int', 'simple_listings_items_per_page'],
				['title', 'simple_listings_displayed_columns'],
				['callback', 'displayed_columns']
			];
		}

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			if (! empty($_POST['simple_listings_board'])) {
				$_POST['simple_listings_category'] = 0;
			}

			$_POST['simple_listings_displayed_columns'] = $_POST['displayed_column'] ?? [];

			checkSession();

			$save_vars = $config_vars;
			$save_vars[] = ['int', 'simple_listings_board'];
			$save_vars[] = ['select', 'simple_listings_displayed_columns', $_POST['displayed_column'] ?? [], 'multiple' => true];

			saveDBSettings($save_vars);
			redirectexit('action=admin;area=modsettings;sa=listings');
		}

		prepareDBSettingContext($config_vars);
	}

	public function whosOnline(array $actions): string
	{
		global $txt, $scripturl;

		$result = '';

		if (! empty($actions['action']) && $actions['action'] === 'listings') {
			$result = sprintf($txt['simple_listings_who_main'], $scripturl . '?action=listings');
		}

		return $result;
	}

	private function getAllCategories(): array
	{
		global $sourcedir;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = [
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true
		];

		$categories = getBoardList($boardListOptions);

		return array_column($categories, 'name', 'id');
	}

	private function prepareBoardList(): void
	{
		global $smcFunc, $modSettings, $context;

		$request = $smcFunc['db_query']('order_by_board_order', '
			SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE redirect = {string:empty_string}' . (! empty($modSettings['recycle_board']) ? '
				AND b.id_board != {int:recycle_board}' : ''),
			[
				'recycle_board' => empty($modSettings['recycle_board']) ? null : $modSettings['recycle_board'],
				'empty_string'  => ''
			]
		);

		$context['num_boards'] = $smcFunc['db_num_rows']($request);
		$context['categories'] = [];

		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			if (! isset($context['categories'][$row['id_cat']])) {
				$context['categories'][$row['id_cat']] = [
					'id'     => $row['id_cat'],
					'name'   => $row['cat_name'],
					'boards' => []
				];
			}

			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id'          => $row['id_board'],
				'name'        => $row['name'],
				'child_level' => $row['child_level'],
				'selected'    => ! empty($modSettings['simple_listings_board']) && $modSettings['simple_listings_board'] == $row['id_board']
			];
		}

		$smcFunc['db_free_result']($request);
	}

	private function prepareColumns(): void
	{
		global $modSettings, $txt, $context;

		$columns = empty($modSettings['simple_listings_displayed_columns']) ? [] : smf_json_decode($modSettings['simple_listings_displayed_columns']);

		$protect_columns = [2, 5];

		$column_values = [
			$txt['simple_listings_image'],
			$txt['date'],
			$txt['last_post'],
			$txt['board'],
			$txt['topic'],
			$txt['author'],
			$txt['replies'],
			$txt['views']
		];

		$i = 1;
		foreach ($column_values as $value) {
			$context['simple_listings_displayed_columns'][$i] = [
				'id'      => $i,
				'name'    => $value,
				'protect' => in_array($i, $protect_columns)
			];
			$i++;
		}

		foreach ($context['simple_listings_displayed_columns'] as $column) {
			if (in_array($column['id'], $columns) || in_array($column['id'], $protect_columns)) {
				$context['simple_listings_displayed_columns'][$column['id']]['show'] = true;
			}
		}
	}
}
