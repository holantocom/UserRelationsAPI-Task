# System to find user relations

This system using Breadth-first search. It search layer by layer with restrictions on the maximum search depth. If we doesn't have new layer, we load it from database. Loaded layers store in memory and doesn't load twice.

Implementation write, read and delete relations in MySQL.  
User Relations - is an infinite and looped graph.  
Direct relation - is a close friend or foe.  
Indirect relation - is a far friend or foe, thru some number of relations.  

## __construct(PDO $mysql, IUser $user)

This method automatically creates necessary DB structure.

## addFriend(IUser $user): bool

Adds direct friend to target user.

## addFoe(IUser $user): bool

Adds direct foe to target user.

## removeRelation(IUser $user): bool

Removes direct relation for target user.

## isFriend(IUser $user, int $maxScanDepth): bool

Returns TRUE if the specified User is a direct or indirect friend for target user.

## isFoe(IUser $user, int $maxScanDepth): bool

Returns TRUE if the specified User is a direct or indirect foe for target user.

## getAllFriends(int $maxScanDepth): array

Returns list of all direct and indirect friends.

## getConflictUsers(int $maxScanDepth): array

Returns list of users that are friends and foes at the same time for different users in the graph. We can reach the user through friends and foe at the same time.
