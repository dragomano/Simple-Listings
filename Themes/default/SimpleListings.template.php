<?php

function template_simple_listings_above()
{
	global $txt;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<i class="main_icons logs"></i>	', $txt['simple_listings'], '
		</h3>
	</div>';
}

function template_simple_listings_below()
{
	global $modSettings, $txt, $context;

	if (empty($modSettings['simple_listings_category']) && empty($modSettings['simple_listings_board'])) {
		echo '
	<div class="errorbox">', $txt['simple_listings_cat_empty'], '</div>';
	} elseif (!empty($modSettings['simple_listings_category']) && empty($context['boards'])) {
		echo '
	<div class="errorbox">', $txt['simple_listings_no_boards'], '</div>';
	}

	if ($context['can_post_new']) {
		echo '
	<div class="roundframe centertext">';

			if (!empty($context['boards'])) {
				echo '
		<select id="sel_board_go">';

					foreach ($context['boards'] as $category) {
						echo '
			<optgroup label="', $category['name'], '">';

						foreach ($category['boards'] as $board)
							echo '
				<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>
					', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '
				</option>';

						echo '
			</optgroup>';
					}

				echo '
		</select>';
			} elseif (!empty($modSettings['simple_listings_board'])) {
				echo '
		<input type="hidden" id="sel_board_go" value="', (int) $modSettings['simple_listings_board'], '">';
			}

			echo '
		<script>
			function select_board()	{
				let sel_board = document.getElementById("sel_board_go").value;
				window.location = smf_prepareScriptUrl(smf_scripturl) + `action=post;board=${sel_board}.0`;
			}
		</script>
		<input type="button" value="', $txt['simple_listings_post_ad'], '" class="button" onclick="select_board();">
	</div>';
	} else {
		echo '
	<div class="errorbox">', $txt['simple_listings_cannot_post'], '</div>';
	}

	$link = $txt['lang_dictionary'] === 'ru' ? 'https://dragomano.ru/mods/simple-listings' : 'https://custom.simplemachines.org/mods/index.php?mod=3468';

	echo '
	<br class="clear">
	<div class="centertext smalltext">
		<a href="', $link, '" target="_blank" rel="noopener">Simple Listings</a> &copy; 2012&ndash;2024, Bugo
	</div>';
}

function template_callback_select_sl_board()
{
	global $txt, $modSettings, $context;

	echo '
	<dt>
		<span><label>', $txt['simple_listings_board'], '</label></span>
	</dt>
	<dd>
		<a href="#" class="board_selector">[ ', $txt['select_boards_from_list'], ' ]</a>
		<fieldset>
			<legend class="board_selector">
				<a href="#">', $txt['select_boards_from_list'], '</a>
			</legend>
			<label for="sl_board0">
				<input type="radio" id="sl_board0" name="simple_listings_board" value="0"', empty($modSettings['simple_listings_board']) ? ' checked="checked"' : '', '> ', $txt['simple_listings_board_all'], '
			</label>
			<hr>';

	$first = true;

	foreach ($context['categories'] as $category) {
		if (!$first) {
			echo '
			<hr>';
		}

		echo '
			<strong>', $category['name'], '</strong>
			<ul>';

		foreach ($category['boards'] as $board)	{
			echo '
				<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em">
					<label for="sl_board', $board['id'], '">
						<input type="radio" id="sl_board', $board['id'], '" name="simple_listings_board" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', '> ', $board['name'], '
					</label>
				</li>';
		}

		echo '
			</ul>';
			$first = false;
	}

	echo '
		</fieldset>
		<br class="clear">
	</dd>';
}

function template_callback_displayed_columns()
{
	global $context;

	echo '
		<ul class="half_content">';

	$i = 0;
	$limit = ceil(count($context['simple_listings_displayed_columns']) / 2);
	foreach ($context['simple_listings_displayed_columns'] as $column) {
		if ($i == $limit)
			echo '
		</ul>
		<ul class="half_content">';

		echo '
			<li>
				<label for="displayed_column', $column['id'], '">
					<input type="checkbox" id="displayed_column', $column['id'], '" name="displayed_column[', $column['id'], ']" value="', $column['id'], '"', !empty($column['show']) ? ' checked="checked"' : '', $column['protect'] ? ' disabled="true"' : '', '> ', $column['name'], '
				</label>
			</li>';

		$i++;
	}

	echo '
		</ul>
		<br class="clear">';
}
