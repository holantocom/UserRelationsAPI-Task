<?php

class UserRelations implements IUserRelations
{
    private $DB;
    private $user;
    private $friendGraph = array();
    private $foeGraph = array();
    private $visitedFriends = array();

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
        $SQL = "UPDATE user_relations SET isDeleted = 1 WHERE user_id = ? AND relation_id = ? AND isDeleted = 0;";
        $request = $this->DB->loadData($SQL, [$this->user->getId(), $user->getId()]);
        if($request['affected_rows']) {
            $this->friendGraph[$this->user->getId()] = array_diff($this->friendGraph[$this->user->getId()], (array)$user->getId());
            $this->foeGraph[$this->user->getId()] = array_diff($this->foeGraph[$this->user->getId()], (array)$user->getId());
            return TRUE;
        } else {
            return FALSE;
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
        $answer = array();

        foreach($this->visitedFriends as $user){

            if($user == $this->user->getId())
                continue;

            $answer[] = new User($user);
        }

        return $answer;
    }

    public function getConflictUsers(int $maxScanDepth = 0): array
    {
        $friends = $this->getAllFriends($maxScanDepth);
        $answer = array();

        foreach($friends as $user){

            $isFriend = $this->hasConnection($user, $maxScanDepth, 'friend');
            $isFoe = $this->hasConnection($user, $maxScanDepth, 'foe');
            if($isFriend && $isFoe){
                $answer[] = $user;
            }

        }

        return $answer;
    }


    private function addLinkBetweenUsers(IUser $user, IUser $relation, string $relationType): bool
    {
        //Check that the number of records isn't more than the maximum
        $SQL = "SELECT relation_id FROM user_relations WHERE user_id = ? AND isDeleted = 0;";
        $request = $this->DB->loadData($SQL, (array)$user->getId());

        if($request['count'] > IUserRelations::MAX_DIRECT_RELATIONS)
            return FALSE;

        //Check that the desired user doesn't have duplicates to avoid conflicts and unnecessary queries in the database
        foreach ($request['data'] as $row){

            if($row['relation_id'] == $relation->getId()){
                return FALSE;
            }

        }

        $SQL = "INSERT INTO user_relations (user_id, relation_id, relation_type) VALUE (?, ?, ?);";
        $request = $this->DB->loadData($SQL, [$user->getId(), $relation->getId(), $relationType]);

        if($relationType == 'friend'){
            $this->friendGraph[$user->getId()][] = $relation->getId();
        } else {
            $this->foeGraph[$user->getId()][] = $relation->getId();
        }

        return $request['affected_rows'] ? TRUE : FALSE;
    }

    private function hasConnection(IUser $relation, int $maxScanDepth, string $relationType): bool
    {
        if($maxScanDepth < 0) {
            return FALSE;
        }

        if(!count($this->friendGraph) && !count($this->foeGraph)){

            $SQL = "SELECT relation_id, user_id, relation_type FROM user_relations WHERE isDeleted = 0;";
            $request = $this->DB->loadData($SQL);

            foreach ($request['data'] as $row){

                if($row['relation_type'] == "friend"){
                    $this->friendGraph[$row['user_id']][] = $row['relation_id'];
                } else {
                    $this->foeGraph[$row['user_id']][] = $row['relation_id'];
                }
            }

        }

        $this->visitedFriends = array();
        $graph = $relationType == 'friend' ? $this->friendGraph : $this->foeGraph;
        $maxScanDepth = $maxScanDepth ? $maxScanDepth : PHP_INT_MAX;
        $answer = $this->depthSearch($graph, [], $maxScanDepth, $this->user->getId(), $relation->getId());

        return $answer;
    }

    private function depthSearch($graph, $visited, $depth, $startNode, $endNode)
    {
        if($depth < 0)
            return FALSE;

        if($startNode == $endNode)
            return TRUE;

        if(in_array($startNode, $visited))
            return FALSE;

        $visited[] = $startNode;
        $this->visitedFriends[] = $startNode;

        foreach( ($graph[$startNode] ?? []) as $index => $vertex){
            if(!in_array($vertex, $visited)){
                if($this->depthSearch($graph, $visited, ($depth - 1), $vertex, $endNode))
                    return TRUE;
            }
        }

        return FALSE;
    }

}