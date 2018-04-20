<?php

use UserProfile\UserProfile;

$response = array('success' => false, 'msg' => 'An error occurred');

if (empty($_GET['username'])) {
    $response['msg'] = 'Missing username.';
    echo json_encode($response);
    exit;
}

$profile = new UserProfile($_GET['username'], false);
if (
    !($profile_id = $profile->getProfileId()) ||
    !($project_id = $profile->getProjectId()) ||
    !($username_field = $profile->getUsernameField())
) {
    echo json_encode($response);
    exit;
}

$project = new Project($project_id);
echo json_encode(array(
    'success' => true,
    'data' => array(
        'pid' => $project_id,
        'id' => $profile_id,
        'event_id' => $project->firstEventId,
        'page' => $project->metadata[$username_field]['form_name'],
    ),
));
