<?php

function template_simple_listings_above()
{
	global $txt;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<span class="ie6_header floatleft">
				<i class="icon icon-earth"></i>	', $txt['simple_listings'], '
			</span>
		</h3>
	</div>';
}

function template_simple_listings_below()
{
	global $modSettings, $txt, $context;

	if (empty($modSettings['simple_listings_category']) && empty($modSettings['simple_listings_board'])) {
		echo '
	<div class="information centertext error">', $txt['simple_listings_cat_empty'], '</div>';
	} elseif (!empty($modSettings['simple_listings_category']) && empty($context['boards'])) {
		echo '
	<div class="information centertext error">', $txt['simple_listings_no_boards'], '</div>';
	}

	if ($context['can_post_new']) {
		echo '
	<div class="centertext">
		<span class="clear upperframe"><span></span></span>
		<div class="roundframe">
			<div class="innerframe">';

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
				<input type="hidden" id="sel_board_go" value="', (int) $modSettings['simple_listings_board'], '" />';
			}

			echo '
				<script type="text/javascript">
					function select_board()	{
						let sel_board = document.getElementById("sel_board_go").value;
						window.location = smf_prepareScriptUrl(smf_scripturl) + `action=post;board=${sel_board}.0`;
					}
				</script>
				<input type="button" style="float: none" value="', $txt['simple_listings_post_ad'], '" class="button_submit" onclick="select_board();" />
			</div>
		</div>
		<span class="clear lowerframe"><span></span></span>
	</div>';
	} else {
		echo '
	<div class="information centertext error">', $txt['simple_listings_cannot_post'], '</div>';
	}

	$link = in_array($context['user']['language'], array('russian','russian-utf8'))
		? 'https://dragomano.ru/mods/simple-listings'
		: 'https://custom.simplemachines.org/mods/index.php?mod=3468';

	echo '
	<br class="clear" />
	<div class="centertext smalltext">
		<a href="', $link, '" target="_blank" rel="noopener">Simple Listings</a> &copy; 2012&ndash;2022, Bugo
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
		<ul class="ignoreboards" style="margin: 0; width: auto">
			<li>
				<label for="sl_board0">
					<input type="radio" id="sl_board0" name="simple_listings_board" value="0"', empty($modSettings['simple_listings_board']) ? ' checked="checked"' : '', ' class="input_radio" /> ', $txt['simple_listings_board_all'], '
				</label>
			</li>';

	foreach ($context['categories'] as $category) {
		echo '
			<li class="category">
				<strong>', $category['name'], '</strong>
				<ul>';

		foreach ($category['boards'] as $board)	{
			echo '
					<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em">
						<label for="sl_board', $board['id'], '">
							<input type="radio" id="sl_board', $board['id'], '" name="simple_listings_board" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_radio" /> ', $board['name'], '
						</label>
					</li>';
		}

		echo '
				</ul>
			</li>';
	}

	echo '
		</ul>
		<br class="clear" />
		<br class="clear" />
	</dd>';
}
