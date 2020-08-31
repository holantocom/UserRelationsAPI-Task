<?php

class UserRelations implements IUserRelations
{
    private $DB;
    private $user;
    private $visitedFriends = [];
    private $arrayGraphDepth = ['friend' => 0, 'foe' => 0];
    private $relationGraph = [];

    public function __construct(PDO $mysql, IUser $user)
    {
        Database::createInstance($mysql);
        $this->DB = Database::getInstance();
        $this->user = $user;
    }

    public function addFriend(IUser $user): bool
    {
        return $this->addLinkBetweenUsers($this->user, $user, 'friend');
    }

    public function addFoe(IUser $user): bool
    {
        return $this->addLinkBetweenUsers($this->user, $user, 'foe');
    }

    public function removeRelation(IUser $user): bool
    {
        $userID = $this->user->getId();
        $relationID = $user->getId();

        $SQL = "UPDATE user_relations SET is_deleted = 1 WHERE user_id = ? AND relation_id = ? AND is_deleted = 0;";
        $request = $this->DB->loadData($SQL, [$userID, $relationID]);

        if ($request['affected_rows']) {
            $this->relationGraph['friend'][$userID] = array_diff(
                $this->relationGraph['friend'][$userID] ?? [],
                (array)$relationID
            );
            $this->relationGraph['foe'][$userID] = array_diff(
                $this->relationGraph['foe'][$userID] ?? [],
                (array)$relationID
            );
            return true;
        } else {
            return false;
        }
    }

    public function isFriend(IUser $user, int $maxScanDepth = 0): bool
    {
        return $this->hasConnection($user, $maxScanDepth, 'friend');
    }

    public function isFoe(IUser $user, int $maxScanDepth = 0): bool
    {
        return $this->hasConnection($user, $maxScanDepth, 'foe');
    }

    public function getAllFriends(int $maxScanDepth = 0): array
    {
        $fakeUser = new User(0);
        $this->hasConnection($fakeUser, $maxScanDepth, 'friend');
        $answer = [];

        foreach ($this->visitedFriends as $user) {

            if ($user == $this->user->getId()) {
                continue;
            }

            $answer[] = new User($user);
        }

        return $answer;
    }

    public function getConflictUsers(int $maxScanDepth = 0): array
    {
        $friends = $this->getAllFriends($maxScanDepth);
        $answer = [];

        foreach ($friends as $user) {

            $isFoe = $this->hasConnection($user, $maxScanDepth, 'foe');
            if ($isFoe) {
                $answer[] = $user;
            }

        }

        return $answer;
    }


    private function addLinkBetweenUsers(IUser $user, IUser $relation, string $relationType): bool
    {
        //Check that the number of records isn't more than the maximum
        $SQL = "SELECT relation_id FROM user_relations WHERE user_id = ? AND is_deleted = 0;";
        $request = $this->DB->loadData($SQL, (array)$user->getId());

        if ($request['count'] > IUserRelations::MAX_DIRECT_RELATIONS) {
            return false;
        }

        //Check that the desired user doesn't have duplicates to avoid conflicts and unnecessary queries in the database
        foreach ($request['data'] as $row) {

            if ($row['relation_id'] == $relation->getId()) {
                return false;
            }

        }

        $SQL = "INSERT INTO user_relations (user_id, relation_id, relation_type) VALUE (?, ?, ?);";
        $request = $this->DB->loadData($SQL, [$user->getId(), $relation->getId(), $relationType]);

        $this->relationGraph[$relationType][$user->getId()][] = $relation->getId();

        return $request['affected_rows'];
    }

    private function hasConnection(IUser $relation, int $maxScanDepth, string $relationType): bool
    {
        if ($maxScanDepth < 0) {
            return false;
        }

        $this->visitedFriends = [];
        $maxScanDepth = $maxScanDepth ?: PHP_INT_MAX;
        $answer = $this->widthSearch($maxScanDepth, $this->user->getId(), $relation->getId(), $relationType);

        return $answer;
    }

    private function widthSearch(int $maxDepth, int $startNode, int $endNode, string $relationType): bool
    {
        $searchQueue = [];
        $loadList = [];
        $searched[$startNode] = $startNode;
        $iteration = 1;

        if ($startNode == $endNode) {
            return true;
        }

        //First init direct friends
        if (!$this->arrayGraphDepth[$relationType]) {

            $this->relationGraph['friend'][$startNode] = [];
            $this->relationGraph['foe'][$startNode] = [];

            $SQL = "SELECT 
                        user_id, 
                        relation_type, 
                        relation_id 
                    FROM user_relations 
                    WHERE user_id = ? 
                        AND is_deleted = 0;";
            $request = $this->DB->loadData($SQL, (array)$this->user->getId());

            foreach ($request['data'] as $row) {
                $this->relationGraph[$row['relation_type']][$row['user_id']][] = $row['relation_id'];
            }

            $this->arrayGraphDepth['friend']++;
            $this->arrayGraphDepth['foe']++;
        }

        foreach ($this->relationGraph[$relationType][$startNode] as $value) {
            $searchQueue[] = $value;
        }

        while ($searchQueue || $loadList) {

            //If we cant find connection in this level, load new layer of users
            //This users store in memory and cant load twice
            //For example, we have 2 layers in memory and we try to search in 3 layer, we load only one more layer

            if (!$searchQueue) {

                $iteration++;
                if ($iteration > $maxDepth) {
                    return false;
                }

                if ($this->arrayGraphDepth[$relationType] < $iteration) {

                    //Init
                    foreach ($loadList as $element) {
                        $this->relationGraph[$relationType][$element] = [];
                    }

                    $SQL = "SELECT 
                                user_id, 
                                relation_id 
                            FROM user_relations 
                            WHERE user_id IN (" . implode(',', $loadList) . ") 
                                AND relation_type = ? 
                                AND is_deleted = 0;";
                    $request = $this->DB->loadData($SQL, (array)$relationType);

                    //Load friends and foe
                    foreach ($request['data'] as $row) {
                        $this->relationGraph[$relationType][$row['user_id']][] = $row['relation_id'];
                    }

                    $this->arrayGraphDepth[$relationType]++;

                }


                foreach ($loadList as $element) {
                    foreach ($this->relationGraph[$relationType][$element] as $value) {

                        if (!isset($searched[$value])) {
                            $searchQueue[] = $value;
                        }

                    }
                }

                if (!$searchQueue) {
                    return false;
                }

                $loadList = [];
            }

            $node = array_shift($searchQueue);

            if (!isset($searched[$node])) {

                if ($node == $endNode) {
                    return true;
                }

                $this->visitedFriends[$node] = $node;
                $searched[$node] = $node;
                $loadList[$node] = $node;
            }
        }

        return false;
    }

}