<?php
class PeopleController extends AppController {

	var $name = 'People';
	var $uses = array('Person', 'Team', 'Division', 'Group', 'Province', 'Country');
	var $helpers = array('CropImage');
	var $components = array('ImageCrop', 'Lock');
	var $paginate = array(
		'Person' => array(),
		'Registration' => array(
			'contain' => array('Event' => array('EventType')),
			'order' => array('Registration.created' => 'DESC'),
		),
	);

	function isAuthorized() {
		// Anyone that's logged in can perform these operations
		if (in_array ($this->params['action'], array(
				'sign_waiver',
				'view_waiver',
				'search',
				'teams',
				'photo',
		)))
		{
			return true;
		}

		// People can perform these operations on their own account
		if (in_array ($this->params['action'], array(
				'edit',
				'preferences',
				'photo_upload',
				'photo_resize',
				'registrations',
		)))
		{
			// If a player id is specified, check if it's the logged-in user
			// If no player id is specified, it's always the logged-in user
			$person = $this->_arg('person');
			if (!$person || $person == $this->Auth->user('id')) {
				return true;
			}
		}

		return false;
	}

	function statistics() {
		// Get the list of accounts by status
		$status_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.status',
					'COUNT(Person.id) AS count',
				),
				'group' => 'Person.status',
				'order' => 'Person.status',
				'recursive' => -1,
		));

		// Get the list of accounts by group
		$group_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.group_id',
					'COUNT(Person.id) AS count',
				),
				'group' => 'Person.group_id',
				'order' => 'Person.group_id',
				'recursive' => -1,
		));
		$groups = $this->Person->Group->find('list');

		// Get the list of accounts by gender
		$gender_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.gender',
					'COUNT(Person.id) AS count',
				),
				'group' => 'Person.gender',
				'order' => 'Person.gender DESC',
				'recursive' => -1,
		));

		// Get the list of accounts by age
		$age_count = $this->Person->find('all', array(
				'fields' => array(
					'FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 AS age_bucket',
					'COUNT(Person.id) AS count',
				),
				'conditions' => array(
					array('birthdate !=' => null),
					array('birthdate !=' => '0000-00-00'),
				),
				'group' => 'age_bucket',
				'order' => 'age_bucket',
				'recursive' => -1,
		));

		// Get the list of accounts by year started
		$started_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.year_started',
					'COUNT(Person.id) AS count',
				),
				'group' => 'year_started',
				'order' => 'year_started',
				'recursive' => -1,
		));

		// Get the list of accounts by skill level
		$skill_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.skill_level',
					'COUNT(Person.id) AS count',
				),
				'group' => 'skill_level',
				'order' => 'skill_level DESC',
				'recursive' => -1,
		));

		// Get the list of accounts by city
		$city_count = $this->Person->find('all', array(
				'fields' => array(
					'Person.addr_city',
					'COUNT(Person.id) AS count',
				),
				'group' => 'addr_city HAVING count > 2',
				'order' => 'count DESC',
				'recursive' => -1,
		));

		$this->set(compact('status_count', 'groups', 'group_count', 'gender_count',
				'age_count', 'started_count', 'skill_count', 'city_count'));
	}

	function participation() {
		$min = min(
			date('Y', strtotime($this->Person->Registration->Event->field('open', array(), 'open'))),
			$this->Division->League->field('YEAR(open) AS year', array(), 'year')
		);
		$this->set(compact('min'));

		// Check form data
		if (empty ($this->data)) {
			$this->data = array('download' => true);
			return;
		}
		if ($this->data['start'] > $this->data['end']) {
			$this->Session->setFlash(__('End date cannot precede start date', true), 'default', array('class' => 'info'));
			return;
		}

		// Initialize the data structures
		$participation = array();
		$pos = array('captain' => 0, 'player' => 0);
		$seasons = array_fill_keys(Configure::read('options.season'), array(
				'season' => $pos,
				'tournament' => $pos,
		));
		$years = array_fill_keys(range($this->data['start'], $this->data['end']), $seasons);

		$seasons_found = array_fill_keys(Configure::read('options.season'), array(
				'season' => 0,
				'tournament' => 0,
		));

		$captains = Configure::read('privileged_roster_positions');

		$membership_event_list = $this->Person->Registration->Event->find('all', array(
			// TODO: Fix or remove these hard-coded values
			'conditions' => array('event_type_id' => array(1)),
			'contain' => false,
		));
		$event_names = array();

		for ($year = $this->data['start']; $year <= $this->data['end']; ++ $year) {
			// This report covers membership years, not calendar years
			$start = date('Y-m-d', strtotime('tomorrow', strtotime($this->membershipEnd($year-1))));
			$end = $this->membershipEnd($year);

			// We are interested in teams in divisions that operated this year
			$divisions = $this->Division->find('all', array(
				'conditions' => array(
					'Division.open >=' => $start,
					'Division.open <=' => $end,
				),
				'contain' => array(
					'Team' => array(
						'Person' => array('conditions' => array(
							'TeamsPerson.position' => Configure::read('playing_roster_positions'),
							'TeamsPerson.status' => 1,
						)),
					),
					'League',
				),
			));

			// Consolidate the team data into the person-based array
			foreach ($divisions as $division) {
				foreach ($division['Team'] as $team) {
					foreach ($team['Person'] as $person) {
						if (!array_key_exists($person['id'], $participation)) {
							$participation[$person['id']] = array(
								'Person' => $person,
								'Event' => array(),
								'Division' => $years,
							);
						}

						if ($division['Division']['schedule_type'] == 'tournament') {
							$key = 'tournament';
						} else {
							$key = 'season';
						}
						if (in_array($person['TeamsPerson']['position'], $captains)) {
							$pos = 'captain';
						} else {
							$pos = 'player';
						}
						++ $participation[$person['id']]['Division'][$year][$division['League']['season']][$key][$pos];
						$seasons_found[$division['League']['season']][$key] = true;
					}
				}
			}

			// These arrays get big, and we don't need team data any more
			unset ($divisions);

			// We are interested in memberships that covered this year
			$membership_event_ids = array();
			foreach ($membership_event_list as $event) {
				if ($event['Event']['membership_begins'] >= $start &&
					$event['Event']['membership_ends'] <= $end)
				{
					$event_names[$event['Event']['id']] = $event['Event']['name'];
					$membership_event_ids[] = $event['Event']['id'];
				}
			}

			// We are interested in some other registration events that closed this year
			$events = $this->Person->Registration->Event->find('all', array(
				'conditions' => array(
					'OR' => array(
						'Event.id' => $membership_event_ids,
						'AND' => array(
							'Event.close >' => $this->membershipEnd($year-1),
							'Event.close <' => $this->membershipEnd($year),
							// TODO: Fix or remove these hard-coded values
							'Event.event_type_id' => array(5,6,7),
						),
					),
				),
				'contain' => array(
					'Registration' => array(
						'Person',
						'conditions' => array('payment' => 'Paid'),
					),
				),
				'order' => array('Event.event_type_id', 'Event.open', 'Event.close', 'Event.id'),
			));

			// Consolidate the registrations into the person-based array
			foreach ($events as $event) {
				$event_names[$event['Event']['id']] = $event['Event']['name'];
				foreach ($event['Registration'] as $registration) {
					if (!array_key_exists($registration['person_id'], $participation)) {
						$participation[$registration['person_id']] = array(
							'Person' => $registration['Person'],
							'Event' => array(),
							'Division' => $years,
						);
					}
					$participation[$registration['person_id']]['Event'][$event['Event']['id']] = true;
				}
			}

			// These arrays get big, and we don't need event data any more
			unset ($events);
		}

		usort ($participation, array('Person', 'comparePerson'));

		if ($this->data['download']) {
			$this->RequestHandler->renderAs($this, 'csv');
			$this->set('download_file_name', 'Participation');
			Configure::write ('debug', 0);
		}

		$this->set(compact('event_names', 'seasons_found', 'participation'));
	}

	function retention() {
		$min = min(
			date('Y', strtotime($this->Person->Registration->Event->field('open', array(), 'open'))),
			$this->Division->League->field('YEAR(open) AS year', array(), 'year')
		);
		$this->set(compact('min'));

		// Check form data
		if (empty ($this->data)) {
			$this->data = array('download' => true);
			return;
		}
		if ($this->data['start'] > $this->data['end']) {
			$this->Session->setFlash(__('End date cannot precede start date', true), 'default', array('class' => 'info'));
			return;
		}

		// We are interested in memberships
		$event_list = $this->Person->Registration->Event->find('all', array(
			// TODO: Fix or remove these hard-coded values
			'conditions' => array('event_type_id' => array(1)),
			'contain' => false,
			'order' => array('Event.open', 'Event.close', 'Event.id'),
		));

		$start = date('Y-m-d', strtotime('tomorrow', strtotime($this->membershipEnd($this->data['start'] - 1))));
		$end = $this->membershipEnd($this->data['end']);

		$past_events = array();
		foreach ($event_list as $key => $event) {
			if ($event['Event']['membership_begins'] < $start ||
				$event['Event']['membership_ends'] > $end)
			{
				unset($event_list[$key]);
				continue;
			}

			foreach (array_keys($past_events) as $past) {
				$this->Person->Registration->unbindModel(array('belongsTo' => array('Person', 'Event'), 'hasOne' => array('RegistrationAudit')));
				$people = $this->Person->Registration->find('count', array(
						'conditions' => array(
							'Registration.event_id' => $event['Event']['id'],
							'Registration.payment' => 'Paid',
							"Registration.person_id IN (SELECT person_id FROM registrations WHERE event_id = $past)",
						),
				));
				$past_events[$past][$event['Event']['id']] = $people;
			}

			if (!empty($past_events)) {
				$this->Person->Registration->unbindModel(array('belongsTo' => array('Person', 'Event'), 'hasOne' => array('RegistrationAudit')));
				$event_list[$key]['total'] = $this->Person->Registration->find('count', array(
						'conditions' => array(
							'Registration.event_id' => $event['Event']['id'],
							'Registration.payment' => 'Paid',
							'Registration.person_id IN (SELECT DISTINCT person_id FROM registrations WHERE event_id IN (' . implode(',', array_keys($past_events)) . '))',
						),
				));
			} else {
				$event_list[$key]['total'] = 0;
			}

			$this->Person->Registration->unbindModel(array('belongsTo' => array('Person', 'Event'), 'hasOne' => array('RegistrationAudit')));
			$event_list[$key]['count'] = $this->Person->Registration->find('count', array(
					'conditions' => array(
						'Registration.event_id' => $event['Event']['id'],
						'Registration.payment' => 'Paid',
					),
			));

			$past_events[$event['Event']['id']] = array();
		}

		// The last past events row will be empty
		array_pop($past_events);

		if ($this->data['download']) {
			$this->RequestHandler->renderAs($this, 'csv');
			$this->set('download_file_name', 'Retention');
			Configure::write ('debug', 0);
		}

		$this->set(compact('event_list', 'past_events'));
	}

	function view() {
		$id = $this->_arg('person');
		$my_id = $this->Auth->user('id');

		if (!$id) {
			$id = $my_id;
			if (!$id) {
				$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
				$this->redirect('/');
			}
		}

		$person = $this->Person->readCurrent($id);
		if ($person === false) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}
		$this->set(compact('person'));
		$this->set('is_me', ($id === $my_id));
		$this->set($this->_connections($person));
	}

	function tooltip() {
		$id = $this->_arg('person');
		if (!$id) {
			return;
		}
		$this->Person->contain(array(
			'Upload',
		));

		$person = $this->Person->readCurrent($id);
		if ($person === false) {
			return;
		}
		$this->set(compact('person'));
		$this->set('is_me', ($id === $this->Auth->user('id')));
		$this->set($this->_connections($person));

		Configure::write ('debug', 0);
		$this->layout = 'ajax';
	}

	function _connections($person) {
		$connections = array();

		// Check if the current user is a captain of a team the viewed player is on
		$my_team_ids = $this->Session->read('Zuluru.OwnedTeamIDs');
		$team_ids = Set::extract ('/Team/TeamsPerson[status=' . ROSTER_APPROVED . ']/team_id', $person);
		$on_my_teams = array_intersect ($my_team_ids, $team_ids);
		$connections['is_captain'] = !empty ($on_my_teams);

		// Check if the current user is a coordinator of a division the viewed player is a captain in
		$positions = Configure::read('privileged_roster_positions');
		$my_division_ids = $this->Session->read('Zuluru.DivisionIDs');
		$division_ids = array();
		foreach ($positions as $position) {
			$division_ids = array_merge ($division_ids,
				Set::extract ("/Team/TeamsPerson[position=$position]/../Team/division_id", $person)
			);
		}
		$in_my_divisions = array_intersect ($my_division_ids, $division_ids);
		$connections['is_coordinator'] = !empty ($in_my_divisions);

		// Check if the current user is a captain in a division the viewed player is a captain in
		$captain_in_division_ids = Set::extract ('/Team/division_id', $this->Session->read('Zuluru.OwnedTeams'));
		$opponent_captain_in_division_ids = array();
		foreach ($person['Team'] as $team) {
			if (in_array ($team['TeamsPerson']['position'], $positions)) {
				$opponent_captain_in_division_ids[] = $team['Team']['division_id'];
			}
		}
		$captains_in_same_division = array_intersect ($captain_in_division_ids, $opponent_captain_in_division_ids);
		$connections['is_division_captain'] = !empty ($captains_in_same_division);

		// Check if the current user is on a team the viewed player is a captain of
		$connections['is_my_captain'] = false;
		foreach ($person['Team'] as $team) {
			if (in_array ($team['TeamsPerson']['position'], $positions) &&
				in_array ($team['Team']['id'], $this->Session->read('Zuluru.TeamIDs'))
			) {
				$connections['is_my_captain'] = true;
				break;
			}
		}

		return $connections;
	}

	function edit() {
		$id = $this->_arg('person');
		$my_id = $this->Auth->user('id');

		if (!$id && empty($this->data)) {
			$id = $my_id;
			if (!$id) {
				$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
				$this->redirect('/');
			}
		}
		$this->set(compact('id'));

		// Any time that come here (whether manually or forced), we want to expire the
		// login so that session data will be reloaded. Do this even when it's not a save,
		// because when we use third-party auth modules, this page might just have a
		// link to some other edit page, but we still want to refresh the session next
		// time we load any Zuluru page.
		$this->Session->delete('Zuluru.login_time');

		$this->_loadAddressOptions();
		$this->_loadGroupOptions();

		if (!empty($this->data)) {
			$this->data['Person']['complete'] = true;
			if ($this->Person->save($this->data)) {
				$this->Session->setFlash(sprintf(__('The %s has been saved', true), __('person', true)), 'default', array('class' => 'success'));

				// There may be callbacks to handle
				$components = Configure::read('callbacks.user');
				foreach ($components as $name => $config) {
					$component = $this->_getComponent('User', $name, $this, false, $config);
					$component->onEdit($this->data['Person']);
				}

				if ($this->data['Person']['id'] == $my_id) {
					// Delete the session data, so it's reloaded next time it's needed
					$this->Session->delete('Zuluru.Person');
				}

				$this->redirect('/');
			} else {
				$this->Session->setFlash(sprintf(__('The %s could not be saved. Please correct the errors below and try again.', true), __('person', true)), 'default', array('class' => 'warning'));
			}
		}
		if (empty($this->data)) {
			$this->Person->recursive = -1;
			$this->data = $this->Person->read(null, $id);
		}
	}

	function preferences() {
		$id = $this->_arg('person');
		$my_id = $this->Auth->user('id');

		if (!$id) {
			$id = $my_id;
			if (!$id) {
				$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
				$this->redirect('/');
			}
		}
		$this->set(compact('id'));
		$this->set('person', $this->Person->readCurrent($id));

		$setting = ClassRegistry::init('Setting');
		if (!empty($this->data)) {
			if ($setting->saveAll ($this->data['Setting'], array('validate' => false))) {
				$this->Session->setFlash(sprintf(__('The %s have been saved', true), __('preferences', true)), 'default', array('class' => 'success'));
				// Reload the configuration right away, so it affects any rendering we do now,
				// and rebuild the menu based on any changes.
				$this->Configuration->load($my_id);
				$this->_initMenu();
			} else {
				$this->Session->setFlash(__('Failed to save your preferences', true), 'default', array('class' => 'warning'));
			}
		}

		$this->data = $setting->find('all', array(
				'conditions' => array('person_id' => $id),
		));
	}

	function photo() {
		$file_dir = Configure::read('folders.uploads');
		$photo = $this->Person->Upload->find('first', array(
				'contain' => false,
				'conditions' => array(
					'other_id' => $this->_arg('person'),
					'type' => 'person',
				),
		));
		if (!empty ($photo)) {
			$this->layout = 'file';
			$file = file_get_contents($file_dir . DS . $photo['Upload']['filename']);
			$type = 'image/jpeg';
			$this->set(compact('file', 'type'));
		}
	}

	function photo_upload() {
		$person = $this->_findSessionData('Person', $this->Person);
		$size = 150;
		$this->set(compact('person', 'size'));

		if (!empty ($this->data) && array_key_exists ('image', $this->data)) {
			if (empty ($this->data['image'])) {
				$this->Session->setFlash(__('There was an unexpected error uploading the file. Please try again.', true), 'default', array('class' => 'warning'));
				return;
			}
			if ($this->data['image']['error'] == UPLOAD_ERR_INI_SIZE) {
				$max = ini_get('upload_max_filesize');
				$unit = substr($max,-1);
				if ($unit == 'M' || $unit == 'K') {
					$max .= 'b';
				}
				$this->Session->setFlash(sprintf (__('The selected photo is too large. Photos must be less than %s.', true), $max), 'default', array('class' => 'warning'));
				return;
			}
			if ($this->data['image']['error'] == UPLOAD_ERR_NO_FILE) {
				$this->Session->setFlash(__('You must select a photo to upload', true), 'default', array('class' => 'warning'));
				return;
			}
			if ($this->data['image']['error'] == UPLOAD_ERR_NO_TMP_DIR ||
				$this->data['image']['error'] == UPLOAD_ERR_CANT_WRITE)
			{
				$this->Session->setFlash(__('This system does not appear to be properly configured for photo uploads. Please contact your administrator to have them correct this.', true), 'default', array('class' => 'error'));
				return;
			}
			if ($this->data['image']['error'] != 0 ||
				strpos ($this->data['image']['type'], 'image/') === false)
			{
				$this->log($this->data, 'upload');
				$this->Session->setFlash(__('There was an unexpected error uploading the file. Please try again.', true), 'default', array('class' => 'warning'));
				return;
			}

			// Image was uploaded, ask user to crop it
			$temp_dir = Configure::read('folders.league_base') . DS . 'temp';
			$rand = mt_rand();
			$uploaded = $this->ImageCrop->uploadImage($this->data['image'], $temp_dir, "temp_{$person['id']}_$rand");
			$this->set(compact('uploaded'));
			if (!$uploaded) {
				$this->Session->setFlash(__('Unexpected error uploading the file', true), 'default', array('class' => 'warning'));
			} else {
				$this->render('photo_resize');
			}
		}
	}

	function photo_resize() {
		if (!empty ($this->data)) {
			$person = $this->_findSessionData('Person', $this->Person);
			$size = 150;
			$this->set(compact('person', 'size'));
			$temp_dir = Configure::read('folders.league_base') . DS . 'temp';
			$file_dir = Configure::read('folders.uploads');

			// Crop and resize the image
			$image = $this->ImageCrop->cropImage($size,
					$this->data['x1'], $this->data['y1'],
					$this->data['x2'], $this->data['y2'],
					$this->data['w'], $this->data['h'],
					$file_dir . DS . $person['id'] . '.jpg',
					$temp_dir . DS . $this->data['imageName']);
			if ($image) {
				// Check if we're overwriting an existing photo.
				$photo = $this->Person->Upload->find('first', array(
						'contain' => false,
						'conditions' => array(
							'other_id' => $person['id'],
							'type' => 'person',
						),
				));
				if (empty ($photo)) {
					$this->Person->Upload->save(array(
							'other_id' => $person['id'],
							'type' => 'person',
							'filename' => basename ($image),
					));
				} else {
					$this->Person->Upload->id = $photo['Upload']['id'];
					$this->Person->Upload->saveField ('approved', false);
				}
				$this->Session->setFlash(__('Photo saved, but will not be visible by others until approved', true), 'default', array('class' => 'success'));
			}
			$this->redirect(array('action' => 'view'));
		}
	}

	function approve_photos() {
		$photos = $this->Person->Upload->find('all', array(
				'contain' => array('Person'),
				'conditions' => array('approved' => 0),
		));
		if (empty ($photos)) {
			$this->Session->setFlash(__('There are no photos to approve.', true), 'default', array('class' => 'info'));
			$this->redirect('/');
		}
		$this->set(compact('photos'));
	}

	function approve_photo() {
		Configure::write ('debug', 0);
		$this->layout = 'ajax';

		extract($this->params['named']);
		$this->set($this->params['named']);

		$this->Person->Upload->id = $id;
		$success = $this->Person->Upload->saveField ('approved', true);
		$this->set(compact('success'));

		$person = $this->Person->Upload->read (null, $id);
		$variables = array(
			'%fullname' => $person['Person']['full_name'],
		);

		if (!$this->_sendMail (array (
				'to' => $person,
				'config_subject' => 'photo_approved_subject',
				'config_body' => "photo_approved_body",
				'variables' => $variables,
				'sendAs' => 'text',
		)))
		{
			$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $person['Person']['email']), 'default', array('class' => 'error'), 'email');
		}
	}

	function delete_photo() {
		Configure::write ('debug', 0);
		$this->layout = 'ajax';

		extract($this->params['named']);
		$this->set($this->params['named']);

		$photo = $this->Person->Upload->read(null, $id);
		if (empty ($photo)) {
			$success = false;
		} else {
			$success = $this->Person->Upload->delete ($id);
			if ($success) {
				$file_dir = Configure::read('folders.uploads');
				unlink($file_dir . DS . $photo['Upload']['filename']);
			}
		}
		$this->set(compact('success'));

		$variables = array(
			'%fullname' => $photo['Person']['full_name'],
		);

		if (!$this->_sendMail (array (
				'to' => $photo,
				'config_subject' => 'photo_deleted_subject',
				'config_body' => "photo_deleted_body",
				'variables' => $variables,
				'sendAs' => 'text',
		)))
		{
			$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $photo['Person']['email']), 'default', array('class' => 'error'), 'email');
		}
	}

	function sign_waiver() {
		$type = $this->_arg('type');
		if ($type == null || !array_key_exists ($type, Configure::read('options.waiver_types'))) {
			$this->Session->setFlash(__('Unknown waiver type', true), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		$id = $this->Auth->user('id');
		if (!$id) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}
		$this->Person->contain ('Waiver');
		$person = $this->Person->read(null, $id);

		// Make sure it's either this year or next year they're waivering for
		$current = $this->membershipYear();
		$year = $this->_arg('year');
		if ($year == null) {
			$year = $current;
		}
		$expiry = $this->membershipEnd($year) . ' 23:59:59';

		$waiver = $this->_findWaiver ($person['Waiver'], $expiry);
		if ($waiver != null) {
			$this->Session->setFlash(__('You have already accepted this waiver', true), 'default', array('class' => 'info'));
			$this->redirect('/');
		}
		$this->set(compact('person', 'waiver'));

		if ($year != $current && $year != $current+1) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('membership year', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		if (!empty ($this->data)) {
			if ($this->data['Person']['signed'] == 'yes') {
				if ($this->Person->Waiver->save (array(
						'person_id' => $id,
						'type' => $type,
						'expires' => $expiry,
				)))
				{
					// By deleting the waivers session variable, the next page will reload them
					$this->Session->delete('Zuluru.Waivers');
					$this->Session->setFlash(__('Waiver signed.', true), 'default', array('class' => 'success'));
					$event = $this->_arg('event');
					if ($event) {
						$this->redirect(array('controller' => 'registrations', 'action' => 'register', 'event' => $event));
					} else {
						$this->redirect('/');
					}
				} else {
					$this->Session->setFlash(__('Failed to save the waiver.', true), 'default', array('class' => 'warning'));
				}
			} else {
				$this->Session->setFlash(__('Sorry, you may only proceed with registration by agreeing to the waiver.', true), 'default', array('class' => 'warning'));
			}
		}
		$this->set(compact('type', 'year'));
	}

	function view_waiver() {
		$type = $this->_arg('type');
		if ($type == null || !array_key_exists ($type, Configure::read('options.waiver_types'))) {
			$this->Session->setFlash(__('Unknown waiver type', true), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		$id = $this->Auth->user('id');
		if (!$id) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}
		$this->Person->contain ('Waiver');
		$person = $this->Person->read(null, $id);

		// Make sure it's either this year or next year they're waivering for
		$current = $this->membershipYear();
		$year = $this->_arg('year');
		if ($year == null) {
			$year = $current;
		}

		$waiver = $this->_findWaiver ($person['Waiver'], $this->membershipEnd ($year));
		$this->set(compact('person', 'waiver'));

		if ($year != $current && $year != $current+1) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('membership year', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		$this->set(compact('type', 'year'));
	}

	function delete() {
		if (!Configure::read('feature.manage_accounts')) {
			$this->Session->setFlash (__('This system uses ' . Configure::read('feature.manage_name') . ' to manage user accounts. Account deletion through Zuluru is disabled.', true), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		$id = $this->_arg('person');
		if (!$id) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect('/');
		}

		// TODO: Don't delete people that have paid registration history, are on team rosters, division coordinators, or the only admin

		// TODO Handle deletions
		$this->Session->setFlash(sprintf(__('Deleting %s is disabled', true), 'players'), 'default', array('class' => 'info'));
		$this->redirect('/');

		if ($this->Person->delete($id)) {
			$this->Session->setFlash(sprintf(__('%s deleted', true), __('Person', true)), 'default', array('class' => 'success'));
			// TODO: Unwind any registrations, including calling event_obj for additional processing like deleting team records
			$this->redirect('/');
		}
		$this->Session->setFlash(sprintf(__('%s was not deleted', true), __('Person', true)), 'default', array('class' => 'warning'));
		$this->redirect('/');
	}

	function search() {
		$params = $url = $this->_extractSearchParams();
		if (!empty($params)) {
			$test = trim (@$params['first_name'], ' *') . trim (@$params['last_name'], ' *');
			if (strlen ($test) < 2) {
				$this->set('short', true);
			} else {
				$this->_mergePaginationParams();
				$this->paginate['Person'] = array(
					'conditions' => $this->_generateSearchConditions($params),
					'contain' => array('Upload'),
				);
				$this->set('people', $this->paginate('Person'));
			}
		}
		$this->set(compact('url'));
	}

	function list_new() {
		$new = $this->Person->find ('all', array(
			'conditions' => array(
				'status' => 'new',
				'complete' => 1,
			),
			'order' => array('last_name' => 'DESC', 'first_name' => 'DESC'),
		));
		foreach ($new as $key => $person) {
			$duplicates = $this->Person->findDuplicates($person);
			$new[$key]['Person']['duplicate'] = !empty($duplicates);
		}

		$this->set(compact('new'));
	}

	function approve() {
		if (!empty ($this->data)) {
			if (empty ($this->data['Person']['disposition'])) {
				$id = $this->data['Person']['id'];
				$this->Session->setFlash(__('You must select a disposition for this account', true), 'default', array('class' => 'info'));
			} else {
				$this->_approve();
				$this->redirect(array('action' => 'list_new'));
			}
		} else {
			$id = $this->_arg('person');
		}

		if (!$id) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect(array('action' => 'list_new'));
		}

		$this->Person->recursive = -1;
		$person = $this->Person->read(null, $id);
		if (!$person) {
			$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
			$this->redirect(array('action' => 'list_new'));
		}
		if ($person['Person']['status'] != 'new') {
			$this->Session->setFlash(__('That account has already been approved', true), 'default', array('class' => 'info'));
			$this->redirect(array('action' => 'list_new'));
		}

		$duplicates = $this->Person->findDuplicates($person);
		$auth = $this->Auth->authenticate->read(null, $id);

		$this->set(compact('person', 'duplicates', 'auth'));
	}

	function _approve() {
		if (strpos ($this->data['Person']['disposition'], ':') !== false) {
			list($disposition,$dup_id) = split(':', $this->data['Person']['disposition']);
		} else {
			$disposition = $this->data['Person']['disposition'];
			$dup_id = null;
		}

		$this->Person->recursive = -1;
		$person = $this->Person->read(null, $this->data['Person']['id']);
		if (!empty ($dup_id)) {
			$existing = $this->Person->read(null, $dup_id);
		}

		// TODO: Some of these require updates/deletions in the settings table
		switch($disposition) {
			case 'approved_player':
				$data = array(
					'id' => $person['Person']['id'],
					// TODO: 'Player' is hard-coded here, but also in the database
					'group_id' => $this->Person->Group->field('id', array('name' => 'Player')),
					'status' => 'active',
				);
				$saved = $this->Person->save ($data, false, array_keys ($data));
				if (!$saved) {
					$this->Session->setFlash(__('Couldn\'t save new member activation', true), 'default', array('class' => 'warning'));
					$this->redirect(array('action' => 'approve', 'person' => $person['Person']['id']));
				}

				$variables = array(
					'%fullname' => $saved['Person']['full_name'],
					'%memberid' => $this->data['Person']['id'],
					'%username' => $saved['Person']['user_name'],
				);

				if (!$this->_sendMail (array (
						'to' => $person,
						'config_subject' => 'approved_subject',
						'config_body' => "{$disposition}_body",
						'variables' => $variables,
						'sendAs' => 'text',
				)))
				{
					$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $person['Person']['email']), 'default', array('class' => 'error'), 'email');
				}
				break;

			case 'approved_visitor':
				$data = array(
					'id' => $person['Person']['id'],
					// TODO: 'Non-player account' is hard-coded here, but also in the database
					'group_id' => $this->Person->Group->field('id', array('name' => 'Non-player account')),
					'status' => 'inactive',
				);
				$saved = $this->Person->save ($data, false, array_keys ($data));
				if (!$saved) {
					$this->Session->setFlash(__('Couldn\'t save new member activation', true), 'default', array('class' => 'warning'));
					$this->redirect(array('action' => 'approve', 'person' => $person['Person']['id']));
				}

				$variables = array(
					'%fullname' => $saved['Person']['full_name'],
					'%username' => $saved['Person']['user_name'],
				);

				if (!$this->_sendMail (array (
						'to' => $person,
						'config_subject' => 'approved_subject',
						'config_body' => "{$disposition}_body",
						'variables' => $variables,
						'sendAs' => 'text',
				)))
				{
					$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $person['Person']['email']), 'default', array('class' => 'error'), 'email');
				}
				break;

			case 'delete':
				if (method_exists ($this->Auth->authenticate, 'delete_duplicate_user')) {
					$this->Auth->authenticate->delete_duplicate_user($person['Person']['id']);
				}
				if (! $this->Person->delete($person['Person']['id']) ) {
					$this->Session->setFlash(sprintf (__('Failed to delete %s', true), $person['Person']['full_name']), 'default', array('class' => 'warning'));
				}
				break;

			case 'delete_duplicate':
				if (method_exists ($this->Auth->authenticate, 'delete_duplicate_user')) {
					$this->Auth->authenticate->delete_duplicate_user($person['Person']['id']);
				}

				if (! $this->Person->delete($person['Person']['id']) ) {
					$this->Session->setFlash(sprintf (__('Failed to delete %s', true), $person['Person']['full_name']), 'default', array('class' => 'warning'));
					break;
				}

				$variables = array(
					'%fullname' => $person['Person']['full_name'],
					'%username' => $person['Person']['user_name'],
					'%existingusername' => $existing['Person']['user_name'],
					'%existingemail' => $existing['Person']['email'],
					'%passwordurl' => Router::url (Configure::read('urls.password_reset'), true),
				);

				if (!$this->_sendMail (array (
						'to' => array($person['Person'], $existing['Person']),
						'config_subject' => "{$disposition}_subject",
						'config_body' => "{$disposition}_body",
						'variables' => $variables,
						'sendAs' => 'text',
				)))
				{
					$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $person['Person']['email']), 'default', array('class' => 'error'), 'email');
				}
				break;

			// This is basically the same as the delete duplicate, except
			// that some old information (e.g. user ID) is preserved
			case 'merge_duplicate':
				$transaction = new DatabaseTransaction($this->Person);
				if (method_exists ($this->Auth->authenticate, 'merge_duplicate_user')) {
					$this->Auth->authenticate->merge_duplicate_user($person['Person']['id'], $existing['Person']['id']);
				}

				// Update all related records
				foreach ($this->Person->hasMany as $class => $details) {
					$this->Person->$class->updateAll(
						array($details['foreignKey'] => $dup_id),
						array($details['foreignKey'] => $person['Person']['id'])
					);
				}

				foreach ($this->Person->hasAndBelongsToMany as $class => $details) {
					if (array_key_exists ('with', $details)) {
						$this->Person->$class->{$details['with']}->updateAll(
							array($details['foreignKey'] => $dup_id),
							array($details['foreignKey'] => $person['Person']['id'])
						);
					}
				}

				if (! $this->Person->delete($person['Person']['id'], false) ) {
					$this->Session->setFlash(sprintf (__('Failed to delete %s', true), $person['Person']['full_name']), 'default', array('class' => 'warning'));
					break;
				}

				// Unset a few fields that we want to retain from the old record
				foreach (array('group_id', 'status') as $field) {
					unset ($person['Person'][$field]);
				}
				$person['Person']['id'] = $dup_id;

				$saved = $this->Person->save ($person);
				if (!$saved) {
					$this->Session->setFlash(__('Couldn\'t save new member information', true), 'default', array('class' => 'warning'));
					break;
				} else {
					$transaction->commit();
				}

				$variables = array(
					'%fullname' => $person['Person']['full_name'],
					'%username' => $person['Person']['user_name'],
					'%existingusername' => $existing['Person']['user_name'],
					'%existingemail' => $existing['Person']['email'],
					'%passwordurl' => Router::url (Configure::read('urls.password_reset'), true),
				);

				if (!$this->_sendMail (array (
						'to' => array($person['Person'], $existing['Person']),
						'config_subject' => "{$disposition}_subject",
						'config_body' => "{$disposition}_body",
						'variables' => $variables,
						'sendAs' => 'text',
				)))
				{
					$this->Session->setFlash(sprintf (__('Error sending email to %s', true), $person['Person']['email']), 'default', array('class' => 'error'), 'email');
				}
				break;
		}
	}

	// This function takes the parameter the old-fashioned way, to try to be more third-party friendly
	function ical($id) {
		$this->layout = 'ical';
		if (!$id) {
			return;
		}

		// Check that the person has enabled this option
		$this->Person->contain(array(
				'Setting',
		));
		$person = $this->Person->readCurrent($id);
		$enabled = Set::extract ('/Setting[name=enable_ical]/value', $person);
		if (empty ($enabled) || !$enabled[0]) {
			return;
		}

		$team_ids = Set::extract ('/Team/id', $person['Team']);
		if (!empty ($team_ids)) {
			$games = $this->Division->Game->find ('all', array(
				'conditions' => array(
					'OR' => array(
						'HomeTeam.id' => $team_ids,
						'AwayTeam.id' => $team_ids,
					),
					'Game.published' => true,
				),
				'fields' => array(
					'Game.id', 'Game.home_team', 'Game.home_score', 'Game.away_team', 'Game.away_score', 'Game.status', 'Game.division_id', 'Game.created', 'Game.updated',
					'GameSlot.game_date', 'GameSlot.game_start', 'GameSlot.game_end',
					'HomeTeam.id', 'HomeTeam.name',
					'AwayTeam.id', 'AwayTeam.name',
				),
				'contain' => array(
					'GameSlot' => array('Field' => 'Facility'),
					'ScoreEntry' => array('conditions' => array('ScoreEntry.team_id' => $team_ids)),
					'HomeTeam',
					'AwayTeam',
				),
				'order' => 'GameSlot.game_date ASC, GameSlot.game_start ASC',
			));

			// Game iCal element will handle team_id as an array
			$this->set('team_id', $team_ids);
			$this->set('games', $games);
		}

		$this->set ('calendar_type', 'Player Schedule');
		$this->set ('calendar_name', "{$person['Person']['full_name']}\'s schedule");

		Configure::write ('debug', 0);
	}

	function registrations() {
		$id = $this->_arg('person');
		$my_id = $this->Auth->user('id');

		if (!$id) {
			$id = $my_id;
			if (!$id) {
				$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
				$this->redirect('/');
			}
		}

		$this->Person->recursive = -1;
		$this->set('person', $this->Person->read(null, $id));
		$this->set('registrations', $this->paginate ('Registration', array('person_id' => $id)));
	}

	function teams() {
		$id = $this->_arg('person');
		$my_id = $this->Auth->user('id');

		if (!$id) {
			$id = $my_id;
			if (!$id) {
				$this->Session->setFlash(sprintf(__('Invalid %s', true), __('person', true)), 'default', array('class' => 'info'));
				$this->redirect('/');
			}
		}

		$this->Person->recursive = -1;
		$this->set('person', $this->Person->read(null, $id));
		$this->set('teams', array_reverse($this->Person->Team->readByPlayerId($id, false)));
	}

	function cron() {
		$this->layout = 'bare';

		if (!$this->Lock->lock ('cron')) {
			return false;
		}

		if (Configure::read('feature.registration')) {
			$types = $this->Person->Registration->Event->EventType->find ('list', array(
					'fields' => 'id',
					'conditions' => array('type' => 'membership'),
			));
			$events = $this->Person->Registration->Event->find ('all', array(
					'conditions' => array('event_type_id' => $types)
			));

			$year = $this->membershipYear();
			$now = time();

			$current = array();
			foreach ($events as $event) {
				if (array_key_exists('membership_begins', $event['Event']) &&
					strtotime ($event['Event']['membership_begins']) < $now &&
					$now < strtotime ($event['Event']['membership_ends']))
				{
					$current[] = $event['Event']['id'];
				}
			}

			$people = $this->Person->find ('all', array(
					'conditions' => array(
						array('Person.id IN (SELECT DISTINCT person_id FROM registrations WHERE event_id IN (' . implode (',', $current) . ') AND payment = "Paid")'),
						array("Person.id NOT IN (SELECT secondary_id FROM activity_logs WHERE type = 'email_membership_letter' AND primary_id = $year)"),
					),
			));

			$emailed = 0;
			$activity = array();
			foreach ($people as $person) {
				// Send the email
				$variables = array(
					'%fullname' => $person['Person']['full_name'],
					'%firstname' => $person['Person']['first_name'],
					'%lastname' => $person['Person']['last_name'],
					'%year' => $year
				);
				if ($this->_sendMail (array (
						'to' => $person,
						'config_subject' => 'member_letter_subject',
						'config_body' => 'member_letter_body',
						'variables' => $variables,
						'sendAs' => 'text',
				)))
				{
					$activity[] = array(
						'type' => 'email_membership_letter',
						'primary_id' => $year,
						'secondary_id' => $person['Person']['id'],
					);
					++ $emailed;
				}
			}

			$this->set(compact ('emailed'));
			// Update the activity log
			$log = ClassRegistry::init ('ActivityLog');
			$log->saveAll ($activity);
		}

		$this->Lock->unlock();
	}
}
?>
