# Usage

## Importand
Any changes to Tags will not update the Permissions, you must manualy save the Permissions on the Object from where Permissions should be updated.
Any new Objects will have the Tag's applied automatically, if the [Configuration](config/Info.md#basic) has been done Correctly

## Ignor "Everyone Else" Permission
In the Bookstack-Tag's section add the [`bookstack/permission/ignorFallbackTag`](config/Info.md#permission) tag to disbale the inheritance of the "Everyone Else" permission.

## Special Permissions
In the Bookstack-Tag's section add the [`bookstack/permission/tag`](config/Info.md#permission) tag and provide Permission-Entries seperated by a [`bookstack/permission/entrySeperator`](config/Info.md#permission), the Permissions will be applied to Objects within the Object.

### Permission-Entry
{Rolename}{[`bookstack/permission/permissionIdentifier`](config/Info.md#permission)}{Permission-Value}

### Permission-Value
The Permission-Value is constructed of 4 diffrent letters
| Letter |        Granted Permission      |
|--------|--------------------------------|
|   v    |         View the Object        |
|   u    |        Update the Object       |
|   d    |        Delete the Object       |
|   c    |  Create Objects in the Object  |

