<?php
require_once('lib/web_data.php');
require_once('lib/api_shared.php');
require_once('lib/permission.php');

global /*$shelves, $books, $chapters, $bookshelfMap,*/ $REQUEST_DATA;

$REQUEST_DATA = new RequestData();

if ($REQUEST_DATA->Method !== 'post') 
{
    http_response_code(405);
    error_log('Invalid Method "' . $REQUEST_DATA->Method . '"');
    die('Invalid Method');
}

/*
$shelves = array();
$books = array();
$chapters = array();

$bookshelfMap = array();
$slugMap = array();

foreach (sendRequest('api/shelves', 'GET', null)['data'] as $shelf)
{
    $shelfEntry = array(
        'books' => array(),
        'tags' => array()
    );
    $details = sendRequest('api/shelves/'. $shelf['id'], 'GET', null);
    foreach($details['books'] as $book)
    {
        $shelfEntry['books'][count($shelfEntry['books'])] = $book['id'];

        if (!isset($bookshelfMap[$book['id']])) $bookshelfMap[$book['id']] = array();
        $bookshelfMap[$book['id']][count($bookshelfMap[$book['id']])] = $shelf['id'];
    }
    foreach($details['tags'] as $tag)
    {
        $shelfEntry['tags'][$tag['name']] = $tag['value'];
    }

    $shelves[$shelf['id']] = $shelfEntry;
    $slugMap[$shelf['slug']] = array(
        'type' => 'bookshelf',
        'id' => $shelf['id']
    );
}

foreach (sendRequest('api/books', 'GET', null)['data'] as $book)
{
    $books[$book['id']] = array(
        'name' => $book['name'],
        'tags' => array(),
        'chapters' => array(),
        'pages' => array()
    );

    foreach (sendRequest('api/books/' . $book['id'], 'GET', null)['tags'] as $tag)
    {
        $books[$book['id']]['tags'][$tag['name']] = $tag['value'];

        foreach ($book['contents'] as $contentEntry)
        {
            switch ($contentEntry['type'])
            {
                case 'chapter': $books[$book['id']]['chapters'][] = $contentEntry['id']; break;
                case 'page': $books[$book['id']]['pages'][] = $contentEntry['id']; break;
            }
        }
    }

    $slugMap[$book['slug']] = array(
        'type' => 'book',
        'id' => $book['id']
    );
}

foreach (sendRequest('api/chapters', 'GET', null)['data'] as $chapter)
{
    $chapters[$chapter['id']] = array(
        'name' => $chapter['name'],
        'book_id' => $chapter['book_id'],
        'tags' => array(),
        'pages' => array()
    );

    foreach (sendRequest('api/chapters/' . $chapter['id'], 'GET', null)['tags'] as $tag)
    {
        $chapters[$chapter['id']]['tags'][$tag['name']] = $tag['value'];

        foreach ($book['pages'] as $page)
        {
            $chapters[$chapter['id']]['pages'][] = $page['id'];
        }
    }
    
    $slugMap[$chapter['slug']] = array(
        'type' => 'chapter',
        'id' => $chapter['id']
    );
}
*/

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
    global $REQUEST_DATA;// $bookshelfMap;

    switch ($REQUEST_DATA->Content['event'])
    {
        case 'book_create':
            loadShelfData();
            $bookId = $REQUEST_DATA->Content['related_item']['id'];
            $shelfId = $GLOBALS['_l_data']['book_map'][$bookId][0];
            //$shelfId = $bookshelfMap[$bookId][0];
            
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

    /*
    global $slugMap, $shelves, $books, $chapters;

    if (isset($slugMap[$REQUEST_DATA->Content['related_item']['slug']]))
    {

        $slugEntry = $slugMap[$REQUEST_DATA->Content['related_item']['slug']];

        switch ($slugEntry['type'])
        {
            case 'bookshelf':
                foreach ($shelves[$slugEntry['id']]['books'] as $book) updatePermissionBook($book, $slugEntry['id'], false);
                break;

            case 'book':
                            
                foreach ($books[$slugEntry['id']]['chapters'] as $chapter)
                {
                    updatePermissionChapter($chapter, $slugEntry['id'], false);
                }

                foreach ($books[$slugEntry['id']]['pages'] as $page)
                {
                    updatePermissionPage($page, $slugEntry['id'], 0);
                }

                break;

            case 'chapter':
                foreach ($chapters[$slugEntry['id']]['pages'] as $page)
                {
                    updatePermissionPage($page, $slugEntry['id']['book_id'], $slugEntry['id']);
                }
                break;
        }
    }
    */
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

    /*
    global $shelves, $books;

    if (isset($shelves[$shelfId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']]))
    {
        $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $shelves[$shelfId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']], array (
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false
        ));
    }

    if (isset($shelves[$shelfId]['tags'][$GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']]))
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
        foreach ($books[$bookId]['chapters'] as $chapter)
        {
            updatePermissionChapter($chapter, $bookId, $cascade);
        }

        foreach ($books[$bookId]['pages'] as $page)
        {
            updatePermissionPage($page, $bookId, 0);
        }
    }
    */

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

    /*
    global $books, $chapters;

    $permissions = sendRequest('api/content-permissions/book/' . $bookId, 'GET', null);
            
    if (isset($books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']]))
    {
        $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']], array (
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false
        ));
    }

    if (isset($books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']]))
    {
        $permissions['fallback_permissions']['inheriting'] = false;
        $permissions['fallback_permissions']['view'] = false;
        $permissions['fallback_permissions']['create'] = false;
        $permissions['fallback_permissions']['update'] = false;
        $permissions['fallback_permissions']['delete'] = false;
    }
    
    $permissions['owner'] = sendRequest('api/content-permissions/chapter/' . $chapterId, 'GET', null)['owner'];
    sendRequest('api/content-permissions/chapter/' . $chapterId, 'PUT', $permissions);

    if ($cascade)
    {
        foreach ($chapters[$chapterId]['pages'] as $page)
        {
            updatePermissionPage($page, $bookId, $chapterId);
        }
    }
    */
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

    /*
    global $books, $chapters;

    $permissions = array();
    if ($chapterId > 0)
    {
        $permissions = sendRequest('api/content-permissions/chapter/' . $chapterId, 'GET', null);
        if (isset($chapters[$chapterId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']]))
        {
            $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $chapters[$chapterId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']], array (
                'view' => false,
                'create' => false,
                'update' => false,
                'delete' => false
            ));
        }
    }
    else
    {
        $permissions = sendRequest('api/content-permissions/book/' . $bookId, 'GET', null);
        if (isset($books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']]))
        {
            $permissions['role_permissions'] = applyPermission($permissions['role_permissions'], $books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['tag']], array (
                'view' => false,
                'create' => false,
                'update' => false,
                'delete' => false
            ));
        }
    }

    if (isset($books[$bookId]['tags'][$GLOBALS['config']['bookstack']['permission']['ignoreFallbackTag']]))
    {
        $permissions['fallback_permissions']['inheriting'] = false;
        $permissions['fallback_permissions']['view'] = false;
        $permissions['fallback_permissions']['create'] = false;
        $permissions['fallback_permissions']['update'] = false;
        $permissions['fallback_permissions']['delete'] = false;
    }

    $permissions['owner'] = sendRequest('api/content-permissions/page/' . $pageId, 'GET', null)['owner'];
    sendRequest('api/content-permissions/page/' . $pageId, 'PUT', $permissions);
    */
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
