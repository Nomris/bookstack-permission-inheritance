<?php
require_once('config/config.php');
require_once('lib/api_shared.php');

global $roleMap;
$roleMap = array();

foreach (sendRequest('api/roles', 'GET', null)['data'] as $role)
{ $roleMap[$role['display_name']] = $role['id']; } 

function applyPermission(array $permissions, string $permissionStr, array $permissionDefaults)
{
    global $roleMap;

    foreach (explode($GLOBALS['config']['bookstack']['permission']['entrySeperator'], $permissionStr) as $permissionEntry)
    {
        $entry = array();

        if (trim($permissionEntry) === '') continue;
        $components = explode($GLOBALS['config']['bookstack']['permission']['permissionIdentifier'], $permissionEntry);
        
        if (!isset($roleMap[$components[0]]))
        {
            error_log('Faild to find Role: ' . $components[0]);
            http_response_code(500);
            die('Faild to find role');
        }
        $entry = array(
            'role_id' => $roleMap[$components[0]],
            'role' => array(
                'id' => $roleMap[$components[0]],
                'display_name' => $components[0]
            )
        );

        foreach ($permissionDefaults as $key => $value)
        {
            $entry[$key] = $value;
        }

        $entry = parsePermission($entry, $components[1]);

        $permissions[] = $entry;
    }

    $permissionsClean = array();
    foreach ($permissions as $permission)
    {
        if (isset($permissionsClean[$permission['role_id']]))
        {
            foreach ($permission as $key => $value)
            {
                if ($key === 'role_id' || $key === 'role') continue;
                if (!isset($permissionDefaults[$key])) continue;

                $permissionsClean[$permission['role_id']][$key] = $permissionsClean[$permission['role_id']][$key] || $permission[$key]; 
            }

            continue;
        }

        $permissionsClean[$permission['role_id']] = $permission;
    }

    return array_values($permissionsClean);
}

function parsePermission(array $permission, string $value)
{
    if (isset($permission['view'])) $permission['view'] = str_contains($value, 'v');
    if (isset($permission['create'])) $permission['create'] = str_contains($value, 'c');
    if (isset($permission['update'])) $permission['update'] = str_contains($value, 'e');
    if (isset($permission['delete'])) $permission['delete'] = str_contains($value, 'd');

    return $permission;
}

?>