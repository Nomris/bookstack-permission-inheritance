<?php
require_once('lib/web_data.php');
require_once('lib/api_shared.php');
require_once('lib/permission.php');

global $REQUEST_DATA;

$REQUEST_DATA = new RequestData();

if ($REQUEST_DATA->Method !== 'post') 
{
    http_response_code(405);
    error_log('Invalid Method "' . $REQUEST_DATA->Method . '"');
    die('Invalid Method');
}

file_put_contents('debug.json', json_encode($REQUEST_DATA) . "\n\n", FILE_APPEND);

switch ($REQUEST_DATA->getQuery('t'))
{
    case 'cc':
        processContentChanged();
        break;

    case 'pc':
        processPermissionChanged();
        break;

    case false:
        http_response_code(401);
        error_log('No traget');
        die('No traget provided');

    default:
        http_response_code(401);
        error_log('Invalid traget');
        die('Invalid traget provided: ' . $REQUEST_DATA->getQuery('t'));
}

function processContentChanged()
{
    global $REQUEST_DATA;

    switch ($REQUEST_DATA->Content['event'])
    {
        case 'book_create':
            loadShelfData();
            $bookId = $REQUEST_DATA->Content['related_item']['id'];
            $shelfId = $GLOBALS['_l_data']['book_map'][$bookId][0];

            updatePermissionBook($bookId, $shelfId, true);
            break;

            
        case 'chapter_create':
            $chapterId = $REQUEST_DATA->Content['related_item']['id'];
            $bookId = $REQUEST_DATA->Content['related_item']['book_id'];

            updatePermissionChapter($chapterId, $bookId, true);
            break;

            
        case 'page_create':
            $pageId = $REQUEST_DATA->Content['related_item']['id'];
            $chapterId = $REQUEST_DATA->Content['related_item']['chapter_id'];
            $bookId = $REQUEST_DATA->Content['related_item']['book_id'];
            
            updatePermissionPage($pageId, $bookId, $chapterId);
            break;

        default:
            http_response_code(401);
            error_log('Not Supported: '. $REQUEST_DATA->Content['event']);
            die('Not Supported');
    }

}

function processPermissionChanged()
{
    global $REQUEST_DATA;

    if (isset($REQUEST_DATA->Content['related_item']['book_id']))
    {
        if (isset($REQUEST_DATA->Content['related_item']['chapter_id'])) return; // Return because the Page does not need to be operated on

        $permissions = sendRequest('api/content-permissions/book/' . $REQUEST_DATA->Content['related_item']['book_id'], 'GET', null);
        foreach(sendRequest('api/chapters/' . $REQUEST_DATA->Content['related_item']['id'], 'GET', null)['pages'] as $page)
        {
            updatePermissionPage($page['id'], $REQUEST_DATA->Content['related_item']['book_id'], $REQUEST_DATA->Content['related_item']['id'], $permissions);
        }

        return;
    }
    
    loadShelfData();

    if (isset($GLOBALS['_l_data']['slugmap'][$REQUEST_DATA->Content['related_item']['slug']]))
    {
        $slugEntry = $GLOBALS['_l_data']['slugmap'][$REQUEST_DATA->Content['related_item']['slug']];

        switch ($slugEntry['type'])
        {
            case 'shelf':

                $permissions = sendRequest('api/content-permissions/bookshelf/' . $slugEntry['id'], 'GET', null);
                foreach ($GLOBALS['_l_data']['shelf_map'][$slugEntry['id']] as $bookId)
                {
                    updatePermissionBook($bookId, $slugEntry['id'], false, $permissions);  
                } 
                break;

            case 'book':

                $permissions = sendRequest('api/content-permissions/book/' . $REQUEST_DATA->Content['related_item']['id'], 'GET', null);
                $book = sendRequest('api/books/' . $slugEntry['id'], 'GET', null);

                foreach ($book['contents'] as $contentEntry)
                {
                    switch ($contentEntry['type'])
                    {
                        case 'chapter': 
                            updatePermissionChapter($contentEntry['id'], $book['id'], false, $permissions);
                            break;

                            
                        case 'page': 
                            updatePermissionPage($contentEntry['id'], $book['id'], 0, $permissions);
                            break;
                    }
                }

                break;
        }
    }
}

function updatePermissionBook(int $bookId, int $shelfId, bool $cascade, array $permissions = null)
{
    if (is_null($permissions)) $permissions = sendRequest('api/content-permissions/bookshelf/' . $shelfId, 'GET', null);

    if (isset($GLOBALS['_l_data']['shelf_tags'][$shelfId][$GLOBALS['config']['bookstack']['permission']['tag']]))
    {
        $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $GLOBALS['_l_data']['shelf_tags'][$shelfId][$GLOBALS['config']['bookstack']['permission']['tag']], array (
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false
        ));
    }
    
    if (isset($GLOBALS['_l_data']['shelf_tags'][$shelfId][$GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']]))
    {
        $permissions['fallback_permissions']['inheriting'] = false;
        $permissions['fallback_permissions']['view'] = false;
        $permissions['fallback_permissions']['create'] = false;
        $permissions['fallback_permissions']['update'] = false;
        $permissions['fallback_permissions']['delete'] = false;
    }

    $permissions['owner'] = sendRequest('api/content-permissions/book/' . $bookId, 'GET', null)['owner'];
    sendRequest('api/content-permissions/book/' . $bookId, 'PUT', $permissions);

    if ($cascade)
    {
        foreach (sendRequest('api/books/' . $bookId, 'GET', null)['contents'] as $contentEntry)
        {
            switch ($contentEntry['type'])
            {
                case 'chapter': 
                    updatePermissionChapter($contentEntry['id'], $bookId, false, $permissions);
                    break;

                    
                case 'page': 
                    updatePermissionPage($contentEntry['id'], $bookId, 0, $permissions);
                    break;
            }
        }
    }
}

function updatePermissionChapter(int $chapterId, int $bookId, bool $cascade, array $permissions = null)
{
    if (is_null($permissions)) $permissions = sendRequest('api/content-permissions/book/' . $bookId, 'GET', null);
    $book = sendRequest('api/books/' . $bookId, 'GET', null);

    foreach ($book['tags'] as $tag)
    {
        switch ($tag['name'])
        {
            case $GLOBALS['config']['bookstack']['permission']['tag']:
                $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $tag['value'], array (
                    'view' => false,
                    'create' => false,
                    'update' => false,
                    'delete' => false
                ));
                break;

            case $GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']:
                $permissions['fallback_permissions']['inheriting'] = false;
                $permissions['fallback_permissions']['view'] = false;
                $permissions['fallback_permissions']['create'] = false;
                $permissions['fallback_permissions']['update'] = false;
                $permissions['fallback_permissions']['delete'] = false;
                break;
        }
    }

    $permissions['owner'] = sendRequest('api/content-permissions/chapter/' . $chapterId, 'GET', null)['owner'];
    sendRequest('api/content-permissions/chapter/' . $chapterId, 'PUT', $permissions);

    if ($cascade)
    {
        foreach(sendRequest('api/chapters/' . $chapterId, 'GET', null)['pages'] as $page)
        {
            updatePermissionPage($page['id'], $bookId, $chapterId, $permissions);
        }
    }
}

function updatePermissionPage(int $pageId, int $bookId, int $chapterId, array $permissions = null)
{
    if ($chapterId > 0)
    {
        if (is_null($permissions)) $permissions = sendRequest('api/content-permissions/chapter/' . $chapterId, 'GET', null);
        $chapter = sendRequest('api/chapters/' . $chapterId, 'GET', null);

        foreach ($chapter['tags'] as $tag)
        {
            switch ($tag['name'])
            {
                case $GLOBALS['config']['bookstack']['permission']['tag']:
                    $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $tag['value'], array (
                        'view' => false,
                        'create' => false,
                        'update' => false,
                        'delete' => false
                    ));
                    break;

                case $GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']:
                    $permissions['fallback_permissions']['inheriting'] = false;
                    $permissions['fallback_permissions']['view'] = false;
                    $permissions['fallback_permissions']['create'] = false;
                    $permissions['fallback_permissions']['update'] = false;
                    $permissions['fallback_permissions']['delete'] = false;
                    break;
            }
        }
    }
    else
    {
        if (is_null($permissions)) $permissions = sendRequest('api/content-permissions/book/' . $bookId, 'GET', null);
        $book = sendRequest('api/books/' . $bookId, 'GET', null);

        foreach ($book['tags'] as $tag)
        {
            switch ($tag['name'])
            {
                case $GLOBALS['config']['bookstack']['permission']['tag']:
                    $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $tag['value'], array (
                        'view' => false,
                        'create' => false,
                        'update' => false,
                        'delete' => false
                    ));
                    break;

                case $GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']:
                    $permissions['fallback_permissions']['inheriting'] = false;
                    $permissions['fallback_permissions']['view'] = false;
                    $permissions['fallback_permissions']['create'] = false;
                    $permissions['fallback_permissions']['update'] = false;
                    $permissions['fallback_permissions']['delete'] = false;
                    break;
            }
        }
    }

    $permissions['owner'] = sendRequest('api/content-permissions/page/' . $pageId, 'GET', null)['owner'];
    sendRequest('api/content-permissions/page/' . $pageId, 'PUT', $permissions);
}


$GLOBALS['_l_data'] = array();

function loadShelfData()
{
    if (isset($GLOBALS['_l_data']['book_map'])) return;

    $GLOBALS['_l_data']['book_map'] = array();
    $GLOBALS['_l_data']['shelf_map'] = array();
    $GLOBALS['_l_data']['shelf_tags'] = array();
    $GLOBALS['_l_data']['slugmap'] = array();

    foreach (sendRequest('api/shelves', 'GET', null)['data'] as $shelf)
    {
        $GLOBALS['_l_data']['slugmap'][$shelf['slug']] = array(
            'type' => 'shelf',
            'id' => $shelf['id']
        );

        $GLOBALS['_l_data']['shelf_map'][$shelf['id']] = array();
        $GLOBALS['_l_data']['shelf_tags'][$shelf['id']] = array();

        $shelfData = sendRequest('api/shelves/'. $shelf['id'], 'GET', null);
            
        foreach ($shelfData['tags'] as $tag) $GLOBALS['_l_data']['shelf_tags'][$shelf['id']][$tag['name']] = $tag['value'];

        foreach ($shelfData['books'] as $book)
        {
            if (!isset($GLOBALS['_l_data']['book_map'][$book['id']])) $GLOBALS['_l_data']['book_map'][$book['id']] = array();
            $GLOBALS['_l_data']['book_map'][$book['id']][] = $shelf['id'];
            
            $GLOBALS['_l_data']['slugmap'][$book['slug']] = array(
                'type' => 'book',
                'id' => $book['id']
            );

            $GLOBALS['_l_data']['shelf_map'][$shelf['id']][] = $book['id'];
        }
    }
}


?>
