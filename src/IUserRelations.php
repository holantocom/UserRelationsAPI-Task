<?php

/**
 * User Relations Class Contract.
 * Implementation must write, read and delete relations in MySQL.
 * User Relations - is an infinite and looped graph.
 * Direct relation - is a close friend or foe.
 * Indirect relation - is a far friend or foe, thru some number of relations.
 */
interface IUserRelations
{
    /**
     * How many direct relations can be added for one user
     */
    public const MAX_DIRECT_RELATIONS = 20;

    /**
     * Constructor.
     * This method automatically creates necessary DB structure.
     * @param PDO $mysql
     * @param IUser $user Target users
     */
    public function __construct(PDO $mysql, IUser $user);

    /**
     * Adds direct friend to target user.
     * Returns false if user cannot be added as friend.
     * @param IUser $user
     * @return bool
     */
    public function addFriend(IUser $user): bool;

    /**
     * Adds direct foe to target user.
     * Returns false if user cannot be added as foe.
     * @param IUser $user
     * @return bool
     */
    public function addFoe(IUser $user): bool;

    /**
     * Removes direct relation for target user
     * @param IUser $user
     * @return bool
     */
    public function removeRelation(IUser $user): bool;

    /**
     * Returns TRUE if the specified User is a direct or indirect friend for target user.
     * This method scans relations graph to find the specified user.
     * @param IUser $user
     * @param int $maxScanDepth Max levels of graph to scan. 0 - is no limit.
     * @return bool
     */
    public function isFriend(IUser $user, int $maxScanDepth = 0): bool;

    /**
     * Returns TRUE if the specified User is a direct or indirect foe for target user.
     * This method scans relations graph to find the specified user.
     * @param IUser $user
     * @param int $maxScanDepth Max levels of graph to scan. 0 - is no limit.
     * @return bool
     */
    public function isFoe(IUser $user, int $maxScanDepth = 0): bool;

    /**
     * Returns list of all direct and indirect friends.
     * @param int $maxScanDepth Max levels of graph to scan. 0 - is no limit.
     * @return IUser[]
     */
    public function getAllFriends(int $maxScanDepth = 0): array;

    /**
     * Returns list of users that are friends and foes at the same time for different users in the graph.
     * For example:
     * - UserA friends with UserB
     * - UserA feud with UserC
     * - UserC friends with UserB
     * for UserA this method will return UserC, because it is friend and foe at the same time for UserA.
     * @param int $maxScanDepth Max levels of graph to scan. 0 - is no limit.
     * @return IUser[]
     */
    public function getConflictUsers(int $maxScanDepth = 0): array;
}