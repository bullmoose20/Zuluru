<?php
$this->Html->addCrumb (__('Games', true));
$this->Html->addCrumb (__('Game', true) . ' ' . $game['Game']['id']);
$this->Html->addCrumb (__('Submit Game Results', true));
?>

<?php
if ($team_id == $game['HomeTeam']['id']) {
	$this_team = $game['HomeTeam'];
	$opponent = $game['AwayTeam'];
} else {
	$this_team = $game['AwayTeam'];
	$opponent = $game['HomeTeam'];
}
$opponent_score = Game::_get_score_entry($game, $opponent['id']);
?>

<div class="games form">
<h2><?php  __('Submit Game Results'); ?></h2>
<p><?php
echo $this->Html->para(null, sprintf(__('Submit %s for the %s game at %s between %s and %s.', true),
	__('the result', true),
	$this->ZuluruTime->date ($game['GameSlot']['game_date']) . ' ' .
		$this->ZuluruTime->time ($game['GameSlot']['game_start']) . '-' .
		$this->ZuluruTime->time ($game['GameSlot']['display_game_end']),
	$this->element('fields/block', array('field' => $game['GameSlot']['Field'], 'display_field' => 'long_name')),
	$this->element('teams/block', array('team' => $this_team, 'show_shirt' => false)),
	$this->element('teams/block', array('team' => $opponent, 'show_shirt' => false))
));
?></p>
<p><?php __('If your opponent has already entered a result, it will be displayed below. If the result you enter does not agree with this result, posting of the result will be delayed until your coordinator can confirm the correct result.'); ?></p>

<?php
echo $this->Form->create(false, array('url' => Router::normalize($this->here)));

if ($opponent_score) {
	$default_status = $opponent_score['status'];
} else {
	$default_status = null;
}
echo $this->Form->input("ScoreEntry.$team_id.status", array(
		'id' => 'Status',
		'label' => __('This game was:', true),
		'options' => array(
			'normal'			=> 'Played',
			'home_default'		=> "Defaulted by {$game['HomeTeam']['name']}",
			'away_default'		=> "Defaulted by {$game['AwayTeam']['name']}",
			'cancelled'			=> 'Cancelled (e.g. due to weather)',
		),
		'default' => $default_status,
));

if (array_key_exists ($team_id, $game['ScoreEntry'])) {
	echo $this->Form->hidden ("ScoreEntry.$team_id.id", array ('value' => $game['ScoreEntry'][$team_id]['id']));
}
?>

<table class="list" id="Scores">
<tr>
	<th><?php __('Team Name'); ?></th>
	<th><?php __('Your Score Entry'); ?></th>
	<th><?php __('Opponent\'s Score Entry'); ?></th>
</tr>
<tr>
	<td><?php echo $this_team['name']; ?></td>
	<td><?php echo $this->ZuluruForm->input("ScoreEntry.$team_id.score_for", array(
			'div' => false,
			'id' => ($team_id == $game['HomeTeam']['id'] ? 'ScoreHome' : 'ScoreAway'),
			'label' => false,
			'type' => 'number',
			'size' => 3,
	)); ?></td>
	<td><?php
	if ($opponent_score) {
		echo $opponent_score['score_against'];
	} else {
		__('not yet entered');
	}
	?></td>
</tr>
<tr>
	<td><?php echo $opponent['name']; ?></td>
	<td><?php echo $this->ZuluruForm->input("ScoreEntry.$team_id.score_against", array(
			'div' => false,
			'id' => ($team_id == $game['HomeTeam']['id'] ? 'ScoreAway' : 'ScoreHome'),
			'label' => false,
			'type' => 'number',
			'size' => 3,
	)); ?></td>
	<td><?php
	if ($opponent_score) {
		echo $opponent_score['score_for'];
	} else {
		__('not yet entered');
	}
	?></td>
</tr>
<?php if (League::hasCarbonFlip($game['Division']['League'])): ?>
<tr id="CarbonFlipRow">
	<td><?php __('Carbon Flip'); ?></td>
	<td><?php
	$carbon_flip_options = array(
			2 => sprintf(__('%s won', true), $game['HomeTeam']['name']),
			0 => sprintf(__('%s won', true), $game['AwayTeam']['name']),
			1 => __('tie', true),
	);
	echo $this->ZuluruForm->input("ScoreEntry.$team_id.home_carbon_flip", array(
			'div' => false,
			'id' => 'CarbonFlip',
			'label' => false,
			'empty' => '---',
			'options' => $carbon_flip_options,
	)); ?></td>
	<td><?php
	if ($opponent_score) {
		echo $carbon_flip_options[$opponent_score['home_carbon_flip']];
	} else {
		__('not yet entered');
	}
	?></td>
</tr>
<?php endif; ?>
</table>

<?php
if (League::hasSpirit($game['Division']['League'])) {
	echo $this->element ('spirit/input', array('team_id' => $opponent['id'],
			'team' => $opponent, 'game' => $game, 'spirit_obj' => $spirit_obj));
}
?>

<?php if (Configure::read('scoring.incident_reports')): ?>
<div id="IncidentWrapper">
<?php
	echo $this->Form->input('Game.incident', array(
			'type' => 'checkbox',
			'value' => '1',
			'label' => __('I have an incident to report', true),
	));
?>
<fieldset id="IncidentDetails">
<legend>Incident Details</legend>
<?php
echo $this->Form->input("Incident.$team_id.type", array(
		'label' => __('Incident Type', true),
		'options' => Configure::read('options.incident_types'),
		'empty' => '---',
));
echo $this->Form->input("Incident.$team_id.details", array(
		'label' => __('Enter the details of the incident', true),
		'cols' => 60,
));
?>
</fieldset>
</div>
<?php endif; ?>

<?php if (Configure::read('scoring.allstars') && $game['Division']['allstars'] != 'never'): ?>
<div id="AllstarWrapper">
<?php
if ($game['Division']['allstars'] == 'optional') {
	echo $this->Form->input('Game.allstar', array(
			'type' => 'checkbox',
			'value' => '1',
			'label' => __('I want to nominate an all-star', true),
	));
}

if ($game['Division']['ratio'] == 'womens') {
	$genders = __('one female', true);
} else if ($game['Division']['ratio'] == 'mens') {
	$genders = __('one male', true);
} else {
	$genders = __('one male and/or one female', true);
}
?>
<fieldset class="AllstarDetails">
<legend><?php __('Allstar Nominations'); ?></legend>
<p><?php
printf(__('You may select %s all-star from the list below', true), $genders);
if ($game['Division']['allstars'] == 'always') {
	echo ', ' . __('if you think they deserve to be nominated as an all-star', true);
}
?>.</p>

<?php
// Build list of allstar options
$players = array();
$player_roles = Configure::read('playing_roster_roles');

if ($game['Division']['allstars_from'] == 'submitter') {
	$roster = $this_team['Person'];
} else {
	$roster = $opponent['Person'];
}

foreach ($roster as $person) {
	$block = $this->element('people/block', array('person' => $person, 'link' => false));
	if (!in_array($person['TeamsPerson']['role'], $player_roles)) {
		$block .= ' (' . __('substitute', true) . ')';
	}
	$players[$person['gender']][$person['id']] = $block;
}

// May need to tweak saved allstar data
$male = $female = null;
if (array_key_exists ('division_id', $this->data['Game']) && !empty($this->data['Allstar'])) {
	foreach ($this->data['Allstar'] as $allstar) {
		if (is_array ($players[$allstar['Person']['gender']])) {
			if (array_key_exists ($allstar['person_id'], $players[$allstar['Person']['gender']])) {
				if ($allstar['Person']['gender'] == 'Male') {
					$male = $allstar['Person']['id'];
					echo $this->Form->hidden('Allstar.0.id', array('value' => $allstar['id']));
				} else {
					$female = $allstar['Person']['id'];
					echo $this->Form->hidden('Allstar.1.id', array('value' => $allstar['id']));
				}
			}
		}
	}
}

if (!empty ($players['Male'])) {
	echo $this->Form->input('Allstar.0.person_id', array(
			'type' => 'radio',
			'legend' => __('Male', true),
			'options' => $players['Male'],
			'value' => $male,
	));
}

if (!empty ($players['Female'])) {
	echo $this->Form->input('Allstar.1.person_id', array(
			'type' => 'radio',
			'legend' => __('Female', true),
			'options' => $players['Female'],
			'value' => $female,
	));
}

$coordinator = __('league coordinator', true);
if (! empty ($game['Division']['League']['coord_list'])) {
	$coordinator = $this->Html->link($coordinator, "mailto:{$game['Division']['League']['coord_list']}");
}
?>

<p><?php printf(__('If you feel strongly about nominating a second male or female please contact your %s.', true), $coordinator); ?></p>
</fieldset>
</div>
<?php endif; ?>

<?php if (League::hasStats($game['Division']['League'])): ?>
<div id="StatsWrapper">
<?php
	echo $this->Form->input('Game.collect_stats', array(
			'type' => 'checkbox',
			'value' => '1',
			'label' => __('I want to enter stats for this game (if you don\'t do it now, you can do it later)', true),
	));
?>
</div>
<?php endif; ?>

<div class="submit">
<?php echo $this->Form->submit(__('Submit', true), array('div' => false)); ?>

<?php echo $this->Form->submit(__('Reset', true), array('div' => false, 'type' => 'reset')); ?>

<?php echo $this->Form->end(); ?>
</div>
</div>

<?php
// There is no harm in calling jQuery functions on empty lists, so we don't
// have to specially account for the cases where the incident or allstar
// checkboxes don't exist.
// Note that the spirit scoring objects must implement the enableSpirit and
// disableSpirit JavaScript functions to handle any non-text input fields.
$win = Configure::read('scoring.default_winning_score');
$lose = Configure::read('scoring.default_losing_score');
echo $this->Html->scriptBlock("
function statusChanged() {
	if (jQuery('#Status').val() == 'home_default') {
		jQuery('#ScoreHome').val($lose);
		jQuery('#ScoreAway').val($win);
		disableCommon();
		enableScores();
	} else if (jQuery('#Status').val() == 'away_default') {
		jQuery('#ScoreHome').val($win);
		jQuery('#ScoreAway').val($lose);
		disableCommon();
		enableScores();
	} else if (jQuery('#Status').val() == 'normal') {
		enableCommon();
		enableScores();
	} else {
		jQuery('#ScoreHome').val(0);
		jQuery('#ScoreAway').val(0);
		disableCommon();
		disableScores();
	}
}

function disableScores() {
	jQuery('#Scores').css('display', 'none');
}

function enableScores() {
	jQuery('#Scores').css('display', '');
}

function disableCommon() {
	jQuery('input:text').prop('disabled', true);
	jQuery('input[type=\"number\"]').prop('disabled', true);
	jQuery('#CarbonFlip').prop('disabled', true);
	jQuery('#CarbonFlipRow').css('display', 'none');
	jQuery('#GameIncident').prop('disabled', true);
	jQuery('#IncidentWrapper').css('display', 'none');
	jQuery('#GameAllstar').prop('disabled', true);
	jQuery('#AllstarWrapper').css('display', 'none');
	jQuery('#SpiritEntryHasMostSpirited').prop('disabled', true);
	jQuery('#MostSpiritedWrapper').css('display', 'none');
	jQuery('#GameCollectStats').prop('disabled', true);
	jQuery('#StatsWrapper').css('display', 'none');
	if (typeof window.disableSpirit == 'function') {
		disableSpirit();
	}
}

function enableCommon() {
	jQuery('input:text').prop('disabled', false);
	jQuery('input[type=\"number\"]').prop('disabled', false);
	jQuery('#CarbonFlip').prop('disabled', false);
	jQuery('#CarbonFlipRow').css('display', '');
	jQuery('#GameIncident').prop('disabled', false);
	jQuery('#IncidentWrapper').css('display', '');
	jQuery('#GameAllstar').prop('disabled', false);
	jQuery('#AllstarWrapper').css('display', '');
	jQuery('#SpiritEntryHasMostSpirited').prop('disabled', false);
	jQuery('#MostSpiritedWrapper').css('display', '');
	jQuery('#GameCollectStats').prop('disabled', false);
	jQuery('#StatsWrapper').css('display', '');
	if (typeof window.enableSpirit == 'function') {
		enableSpirit();
	}
}

function incidentCheckboxChanged() {
	if (jQuery('#GameIncident').prop('checked')) {
		jQuery('#IncidentDetails').css('display', '');
	} else {
		jQuery('#IncidentDetails').css('display', 'none');
	}
}

function allstarCheckboxChanged() {
	if (jQuery('#GameAllstar').prop('checked')) {
		jQuery('.AllstarDetails').css('display', '');
	} else {
		jQuery('.AllstarDetails').css('display', 'none');
	}
}

function mostSpiritedCheckboxChanged() {
	if (jQuery('#SpiritEntry{$opponent['id']}HasMostSpirited').prop('checked')) {
		jQuery('.MostSpiritedDetails').css('display', '');
	} else {
		jQuery('.MostSpiritedDetails').css('display', 'none');
	}
}
");

// Make sure things are set up correctly, in the case that
// invalid data was detected and the form re-displayed.
// Not sure what might be invalid if a "defaulted" status is
// selected, since pretty much everything else is disabled,
// but maybe something in the future. Cost to do this is
// extremely minimal.
$this->Js->buffer("
jQuery('#Status').on('change', function(){statusChanged();});
jQuery('#GameIncident').on('change', function(){incidentCheckboxChanged();});
jQuery('#GameAllstar').on('change', function(){allstarCheckboxChanged();});
jQuery('#SpiritEntry{$opponent['id']}HasMostSpirited').on('change', function(){mostSpiritedCheckboxChanged();});
statusChanged();
incidentCheckboxChanged();
");
if ($game['Division']['allstars'] == 'optional') {
	$this->Js->buffer('allstarCheckboxChanged();');
}
if ($game['Division']['most_spirited'] == 'optional') {
	$this->Js->buffer('mostSpiritedCheckboxChanged();');
}

?>
