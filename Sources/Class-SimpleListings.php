<?php

/**
 * Class-SimpleListings.php
 *
 * @package Simple Listings
 * @link https://dragomano.ru/mods/simple-listings
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2012-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.4
 */

if (! defined('SMF'))
	die('No direct access...');

final class SimpleListings
{
	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', self::class . '::loadTheme#', false);
		add_integration_function('integrate_menu_buttons', self::class . '::menuButtons#', false);
		add_integration_function('integrate_actions', self::class . '::actions#', false);
		add_integration_function('integrate_whos_online', self::class . '::whosOnline#', false);
		add_integration_function('integrate_admin_areas', self::class . '::adminAreas#', false);
		add_integration_function('integrate_admin_search', self::class . '::adminSearch#', false);
		add_integration_function('integrate_modify_modifications', self::class . '::modifyModifications#', false);
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
					'title'       => empty($modSettings['simple_listings_menu_item']) ? $txt['simple_listings_menu'] : $modSettings['simple_listings_menu_item'],
					'href'        => $scripturl . '?action=listings',
					'icon'        => 'logs',
					'show'        => true,
					'sub_buttons' => [
						'settings' => [
							'title'   => $txt['settings'],
							'href'    => $scripturl . '?action=admin;area=modsettings;sa=listings',
							'show'    => allowedTo('admin_forum'),
							'is_last' => true,
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

	public function whosOnline(array $actions): string
	{
		global $txt, $scripturl;

		$result = '';

		if (! empty($actions['action']) && $actions['action'] === 'listings') {
			$result = sprintf($txt['simple_listings_who_main'], $scripturl . '?action=listings');
		}

		return $result;
	}

	public function init(): void
	{
		global $context, $txt, $scripturl;

		loadTemplate('SimpleListings', 'simple_listings');

		$context['page_title']    = $txt['simple_listings'];
		$context['canonical_url'] = $scripturl . '?action=listings';

		$context['linktree'][] = [
			'name' => $context['page_title'],
			'url'  => $scripturl . '?action=listings',
		];

		$this->getTopicData();
	}

	public function getTopicEntries(int $start, int $items_per_page, string $sort): array
	{
		global $smcFunc, $user_info, $modSettings, $txt, $scripturl;

		$board_ids = $this->getBoardIds();
		if (empty($board_ids)) {
			return [];
		}

		$request = $smcFunc['db_query']('', '
			SELECT
				m.id_msg, COALESCE(m.poster_time, 0) AS poster_time, m.id_msg_modified, m.subject, m.body, COALESCE(ml.poster_time, 0) AS last_post,
				' . ($user_info['is_guest'] ? '0' : 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1') . ' AS new_from,
				t.id_topic, t.id_first_msg, t.id_member_started AS user, t.approved, t.is_sticky, t.num_views, t.num_replies,
				b.id_board, b.name, COALESCE(mem.real_name, {string:guest}) AS poster
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($user_info['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:user})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:user})') . '
			WHERE b.id_board IN ({array_int:boards})' . (empty($modSettings['postmod_active']) || allowedTo('approve_posts') ? '' : '
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
			ORDER BY ' . $sort . '
			LIMIT ' . $start . ', ' . $items_per_page,
			[
				'guest'  => $txt['guest_title'],
				'user'   => $user_info['id'],
				'boards' => $board_ids,
				'status' => 1,
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
					'height' => $modSettings['simple_listings_thumb_height'],
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
				'thumb'     => $image,
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
					'is_approved'     => 1,
				]
			);

			$attachments = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$attachments[$row['id_msg']][] = [
					'id'     => $row['id_attach'],
					'url'    => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . ($row['id_attach'] + 1) . ';image',
					'link'   => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image',
					'height' => $modSettings['simple_listings_thumb_height'],
				];
			}

			$smcFunc['db_free_result']($request);

			foreach ($attachments as $id_msg => $data) {
				$entries[$id_msg]['thumb'] = $data[0];
			}
		}

		return $entries;
	}

	public function getNumTopicEntries(): int
	{
		global $smcFunc, $modSettings, $user_info;

		$board_ids = $this->getBoardIds();
		if (empty($board_ids)) {
			return 0;
		}

		$request = $smcFunc['db_query']('', /** @lang text */ '
			SELECT COUNT(t.id_topic)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE b.id_board IN ({array_int:boards})
				AND (t.approved = {int:status}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:user}') . ')',
			[
				'boards' => $board_ids,
				'status' => 1,
				'user'   => $user_info['id'],
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

	public function adminSearch(array $language_files, array $include_files, array &$settings_search): void
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
		global $context, $txt, $scripturl;

		$context['page_title']     = $txt['simple_listings_settings'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=listings';

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['simple_listings_desc'];

		$txt['simple_listings_no_cat'] = sprintf($txt['simple_listings_no_cat'], $scripturl . '?action=admin;area=manageboards;sa=newcat');

		$this->addDefaultSettings();

		if (empty($categories = $this->getAllCategories())) {
			$config_vars = [['desc', 'simple_listings_no_cat']];

			$context['settings_save_dont_show'] = true;
		} else {
			loadTemplate('SimpleListings');

			$this->prepareColumns();

			$config_vars = [
				['check', 'simple_listings_mode'],
				['text', 'simple_listings_menu_item'],
				['boards', 'simple_listings_boards'],
				['int', 'simple_listings_thumb_height'],
				['int', 'simple_listings_items_per_page'],
				['title', 'simple_listings_displayed_columns'],
				['callback', 'displayed_columns'],
			];
		}

		if ($return_config) {
			return $config_vars;
		}

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$_POST['simple_listings_displayed_columns'] = $_POST['displayed_column'] ?? [];

			$save_vars = $config_vars;
			$save_vars[] = ['select', 'simple_listings_displayed_columns', $_POST['displayed_column'] ?? [], 'multiple' => true];

			saveDBSettings($save_vars);
			redirectexit('action=admin;area=modsettings;sa=listings');
		}

		prepareDBSettingContext($config_vars);
	}

	private function addDefaultSettings(): void
	{
		global $modSettings, $txt;

		$addSettings = [];

		if (empty($modSettings['simple_listings_menu_item'])) {
			$addSettings['simple_listings_menu_item'] = $txt['simple_listings_menu'];
		}

		if (empty($modSettings['simple_listings_thumb_height'])) {
			$addSettings['simple_listings_thumb_height'] = 80;
		}

		if (empty($modSettings['simple_listings_items_per_page'])) {
			$addSettings['simple_listings_items_per_page'] = 30;
		}

		if ($addSettings) {
			updateSettings($addSettings);
		}
	}

	private function getTopicData(): void
	{
		global $modSettings, $context, $txt, $scripturl, $sourcedir;

		if (empty($modSettings['simple_listings_mode'])) {
			fatal_lang_error('simple_listings_offmode', false, [], 0);
		}

		$context['template_layers'][] = 'simple_listings';

		$context['can_post_new'] = false;
		$context['sel_category'] = '';

		$board_ids = $this->getBoardIds();
		if (! empty($board_ids)) {
			$context['can_post_new'] = allowedTo('post_new', ...$board_ids)
				|| ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', ...$board_ids));
		}

		$this->prepareColumns();

		$columns = [];

		if (! empty($context['simple_listings_displayed_columns'][1]['show']) && boardsAllowedTo('view_attachments'))
			$columns['image'] = [
				'header' => [
					'value' => $txt['simple_listings_image'],
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
					'class' => 'centertext',
				]
			];

		$columns['date'] = [
			'header' => [
				'value' => $txt['date'],
			],
			'data' => [
				'db' => 'date',
				'class' => 'centertext',
			],
			'sort' => [
				'default' => 'm.poster_time DESC',
				'reverse' => 'm.poster_time',
			]
		];

		if (! empty($context['simple_listings_displayed_columns'][3]['show']))
			$columns['last_post'] = [
				'header' => [
					'value' => $txt['last_post'],
				],
				'data' => [
					'db'    => 'last_post',
					'class' => 'centertext',
				],
				'sort' => [
					'default' => 't.id_last_msg',
					'reverse' => 't.id_last_msg DESC',
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][4]['show']) && empty($modSettings['simple_listings_boards']))
			$columns['section'] = [
				'header' => [
					'value' => $txt['board'],
				],
				'data' => [
					'function' => function ($entry) use ($scripturl) {
						return '<a href="' . $scripturl . '?board=' . $entry['board'] . '.0" target="_blank">' . $entry['name'] . '</a>';
					}
				],
				'sort' => [
					'default' => 'b.name',
					'reverse' => 'b.name DESC',
				]
			];

		$columns['title'] = [
			'header' => [
				'value' => $txt['topic'],
			],
			'data' => [
				'function' => function ($entry) use ($scripturl, $txt) {
					return ($entry['is_new'] ? ' <a href="' . $entry['new_href'] . '" id="newicon' . $entry['msg'] . '" class="new_posts">' . $txt['simple_listings_new'] . '</a> ' : '') . '<a href="' . $scripturl . '?topic=' . $entry['id'] . '.0"' . (! $entry['approved'] ? ' class="error"' : '') . '>' . ($entry['is_sticky'] ? '<strong>' : '') . $entry['title'] . ($entry['is_sticky'] ? '</strong>' : '') . '</a>' . (! $entry['approved'] ? '<br><span class="smalltext">' . $txt['simple_listings_not_approved'] . '</span>' : '');
				}
			],
			'sort' => [
				'default' => 'm.subject',
				'reverse' => 'm.subject DESC',
			]
		];

		if (! empty($context['simple_listings_displayed_columns'][6]['show']))
			$columns['user'] = [
				'header' => [
					'value' => $txt['author'],
				],
				'data' => [
					'function' => function ($entry) use ($txt, $scripturl) {
						return empty($entry['user'])
							? $txt['guest_title']
							: '<a href="' . $scripturl . '?action=profile;u=' . $entry['user'] . '" target="_blank">' . $entry['poster'] . '</a>';
					},
					'class' => 'centertext',
				],
				'sort' => [
					'default' => 'poster',
					'reverse' => 'poster DESC',
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][7]['show']))
			$columns['replies'] = [
				'header' => [
					'value' => $txt['replies'],
				],
				'data' => [
					'db'    => 'replies',
					'class' => 'centertext',
				],
				'sort' => [
					'default' => 't.num_replies',
					'reverse' => 't.num_replies DESC',
				]
			];

		if (! empty($context['simple_listings_displayed_columns'][8]['show']))
			$columns['views'] = [
				'header' => [
					'value' => $txt['views'],
				],
				'data' => [
					'db'    => 'views',
					'class' => 'centertext',
				],
				'sort' => [
					'default' => 't.num_views',
					'reverse' => 't.num_views DESC',
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
				'function' => [$this, 'getTopicEntries'],
			],
			'get_count' => [
				'function' => [$this, 'getNumTopicEntries'],
			],
			'columns' => array_merge(
				$columns,
				[
					'actions' => [
						'header' => [],
						'data'   => [
							'function' => function ($entry) use ($scripturl, $context, $txt) {
								return ($entry['approved'] ? '' : '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . $entry['id'] . '.0;msg=' . $entry['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['approve'] . '"><i class="icon icon-checkbox-checked"></i></a> ') . (allowedTo('admin_forum') || allowedTo('moderate_forum') ? '
								<a href="' . $scripturl . '?action=movetopic;topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['move_topic'] . '"><i class="icon icon-arrow-right"></i></a> ' : '') .
								(allowedTo('admin_forum') || allowedTo('moderate_forum') || $entry['is_own'] ? '<a href="' . $scripturl . '?action=post;msg=' . $entry['msg'] . ';topic=' . $entry['id'] . '.0" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['modify'] . '"><i class="icon icon-pencil"></i></a>
								<a href="' . $scripturl . '?action=removetopic2;topic=' . $entry['id'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ')" title="' . $txt['delete'] . '"><i class="icon icon-bin2"></i></a>' : '');
							},
							'class' => 'simple_listings_actions',
						]
					]
				]
			),
			'form' => [
				'href'          => $scripturl . '?action=listings',
				'include_sort'  => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
				]
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value'    => ! empty($context['sel_category'])
						? $txt['simple_listings_info'] . '<br>' . sprintf($txt['simple_listings_hint'], $context['sel_category'])
						: $txt['simple_listings_info'],
					'class'    => 'smalltext',
					'style'    => 'padding: 0;',
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

	private function getBoardList(): void
	{
		global $modSettings, $smcFunc, $sourcedir, $context;

		$board_ids = $this->getBoardIds();
		if (empty($board_ids))
			return;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = [
			'included_boards' => $board_ids,
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true,
		];

		$context['boards'] = getBoardList($boardListOptions);

		$context['can_post_new'] = allowedTo('post_new', ...$board_ids)
			|| ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics', ...$board_ids));
	}

	private function getBoardIds(): array
	{
		global $modSettings;

		if (empty($modSettings['simple_listings_boards'])) {
			return [];
		}

		return array_map('intval', explode(',', $modSettings['simple_listings_boards']));
	}

	private function getAllCategories(): array
	{
		global $sourcedir;

		require_once($sourcedir . '/Subs-MessageIndex.php');

		$boardListOptions = [
			'ignore_boards'   => true,
			'use_permissions' => true,
			'not_redirection' => true,
		];

		$categories = getBoardList($boardListOptions);

		return array_column($categories, 'name', 'id');
	}

	private function prepareColumns(): void
	{
		global $modSettings, $txt, $context;

		$columns = empty($modSettings['simple_listings_displayed_columns'])
			? []
			: smf_json_decode($modSettings['simple_listings_displayed_columns']);

		$protect_columns = [2, 5];

		$column_values = [
			$txt['simple_listings_image'],
			$txt['date'],
			$txt['last_post'],
			$txt['board'],
			$txt['topic'],
			$txt['author'],
			$txt['replies'],
			$txt['views'],
		];

		$i = 1;
		foreach ($column_values as $value) {
			$context['simple_listings_displayed_columns'][$i] = [
				'id'      => $i,
				'name'    => $value,
				'protect' => in_array($i, $protect_columns),
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
