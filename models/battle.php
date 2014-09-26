<?php

namespace app\models;

class battle extends appModel
{
    protected $table = 'battles';

    protected $name = 'battle';

    protected $alias = 'ba';

    protected $belongsTo = array(
        array('model' => 'game', 'table' => 'games', 'foreign_key' => 'game_id', 'alias' => 'g'),
        array('model' => 'gameArea', 'table' => 'game_areas', 'foreign_key' => 'game_area_id', 'alias' => 'ga')
    );

    protected $hasMany = array(
        array('model' => 'battleArmy', 'table' => 'battle_armies', 'alias' => 'br')
    );

    public static function check($target, $type)
    {
        $battle = static::findByGame_area_id($target->id, array(
            'single' => true,
            'conditions' => array("result != 'Attacker'", "result != 'Defender'", "result != 'Aborted'"),
            'with' => false
        ));

        if ($battle)
        {
            return $battle;
        }

        if ($type == 'support_attack')
        {
            return false;
        }
        elseif ($type == 'attack')
        {
            return static::begin($target);
        }
        elseif ($type == 'defend')
        {
            $attackers = army::find('all', array(
                'with' => array('armyAction'),
                'conditions' => array('aa.target_id' => $target->id, 'aa.action' => 'attack')
            ));

            if (! $attackers)
            {
                return false;
            }
            
            $check = false;
            
            foreach ($attackers as $attacker)
            {
                if ($attacker->faction_id == $target->faction_id)
                {
                    continue;
                }
                
                if (! $battle && ! $check)
                {
                    $battle = static::begin($target);
                    $check = true;
                }
                
                if ($battle)
                {
                    $battle->join($attacker);
                    
                    $armyAction = armyAction::toItem($attacker->armyAction);
                    $armyAction->action_date = null;
                    $armyAction->save();
                }
            }
            
            return ($battle) ? $battle : false;
        }
    }

    public static function begin($target)
    {
        $defenders = army::findByGame_area_id($target->id, array(
            'return' => 'array',
            'with' => array('armyAction'),
            'conditions' => array("(aa.action IS NULL OR (aa.action != 'retreat' AND aa.action != 'redeployment'))")
        ));

        if (empty($defenders))
        {
            return false;
        }
        
        $attackers = armyAction::findByAction('attack', array(
            'return' => 'array',
            'with' => false,
            'conditions' => array('aa.target_id' => $target->id)
        ));
        
        if (empty($attackers))
        {
            return false;
        }

        $battles = battle::find('count', array(
            'return' => 'array',
            'with' => false,
            'conditions' => array('game_area_id' => $target->id)
        ));
        
        $battles ++;

        $ordinalFormat = function($number)
        {
            $suffixes = array('th','st','nd','rd','th','th','th','th','th','th');
            $mod = $number % 100;
            
            return $number . (($mod >= 11 && $mod <= 13) ? 'th' : $suffixes[$number % 10]);
        };

        $data = array(
            'game_id' => $target->game_id,
            'game_area_id' => $target->id,
            'start_time' => time(),
            'battle_name' => $ordinalFormat($battles) .' Battle of ' . $target->area['name'],
            'battle_type' => 'Assault',
            'current_progress' => 50
        );

        $battle = static::create($data);
        $battle->save();
        
        $battleArmies = $armyActions = array();
        
        foreach ($defenders as $defender)
        {
            $battleArmies[] = array(
                'battle_id' => $battle->id,
                'army_id' => $defender['id'],
                'side' => 'defender',
                'joined_time' => $battle->start_time
            );
        }
        
        $supportAttackers = armyAction::findByAction('support_attack', array(
            'return' => 'array',
            'with' => array('army'),
            'conditions' => array('aa.target_id' => $target->id)
        ));

        if ($supportAttackers)
        {
            foreach ($supportAttackers as $attacker)
            {
                $battleArmies[] = array(
                    'battle_id' => $battle->id,
                    'army_id' => $attacker['army_id'],
                    'side' => 'attacker',
                    'joined_time' => $battle->start_time
                );
            }
        }
        
        battleArmy::insert($battleArmies);

        return $battle;
    }

    public function join($battle, $army)
    {
        $battleArmy = battleArmy::find('first', array(
            'return' => 'array',
            'with' => array('army'),
            'conditions' => array('ar.faction_id' => $army->faction_id, 'br.battle_id' => $battle->id)
        ));

        $side = ($battleArmy) ? $battleArmy['side'] : 'attacker';
        
        $data = array(
            'battle_id' => $battle->id,
            'army_id' => $army->id,
            'side' => $side,
            'joined_time' => time()
        );

        $battleArmy = battleArmy::create($data);
        $battleArmy->save();
        
        if ($battleArmy->side == 'attacker')
        {
            $armyAction = armyAction::findByArmy_id($army->id, array(
                'single' => true,
                'with' => false
            ));
            
            if ($armyAction)
            {            
                $armyAction->action_date = null;
                $armyAction->save();
            }
        }

        if ($battle->progress = 'InProgress')
        {
            $attackingUnits = $defendingUnits = 0;
            
            $armies = battleArmy::getArmies($battle->id);

            foreach ($armies['attacker'] as $army)
            {
                $attackingUnits += count($army['gameUnit']);
            }

            foreach ($armies['defender'] as $army)
            {
                $defendingUnits += count($army['gameUnit']);
            }

            $battle->current_progress = round(self::calculateProgress($armies['attacker_organisation'], $attackingUnits, $armies['defender_organisation'], $defendingUnits), 2);
            $battle->save();
        }

        return $this;
    }

    public function leave($battle, $armyId, $type, $organisedRetreat = false)
    {
        battleArmy::update(array('left_time' => time()), array('conditions' => array('battle_id' => $battle->id, 'army_id' => array($armyId))));

        if ($type == 'attacker')
        {
            armyAction::remove(array('conditions' => array('army_id' => $armyId)));

            $attackers = battleArmy::find('count', array(
                'with' => false,
                'conditions' => array('br.side' => 'attacker', 'br.left_time IS NULL', 'br.battle_id' => $battle->id)
            ));

            if ($attackers == 0)
            {
                $battle->end_time = time();
                $battle->current_progress = 0;

                if ($battle->result == 'Starting')
                {
                    $battle->result = 'Aborted';
                    $battle->save();
                }
                else
                {
                    $battle->result = 'Defender';
                    $battle->save();
                    $battle->conclude();
                }
            }

            return 'leave';
        }
        elseif ($type == 'defender')
        {
            $army = army::find($armyId, array(
                'with' => array('gameArea'),
                'fields' => array('ar.*', 'ga.area_id')
            ));

            $activeGame = game::find($army->game_id, array(
                'with' => false,
                'conditions' => array('g.active' => 1),
                'return' => 'array',
                'fields' => array('game_date')
            ));

            $gameDate = strtotime($activeGame['game_date']);

            $defenders = battleArmy::find('count', array(
                'with' => false,
                'conditions' => array('br.side' => 'defender', 'br.left_time IS NULL', 'br.battle_id' => $battle->id)
            ));

            if ($defenders == 0)
            {
                $battle->end_time = time();
                $battle->current_progress = 100;

                $battle->result = 'Attacker';
                $battle->save();
                $battle->conclude();
            }
            
            $otherBattle = battle::find('first', array(
                'with' => array('battleArmy'),
                'single' => true,
                'conditions' => array('br.army_id' => $armyId, 'br.left_time IS NULL')
            ));
            
            if ($otherBattle)
            {
                static::leave($otherBattle, $armyId, 'defender', true);
            }
            
            if ($organisedRetreat)
            {
                return 'retreat';
            }
            
            armyAction::remove(array('conditions' => array('army_id' => $armyId)));
            
            $borders = border::findByArea_1_id($army->gameArea['area_id'], array(
                'return' => 'array',
                'conditions' => array('ga.faction_id' => $army->faction_id),
                'with' => array('gameArea')
            ));

            if ($borders)
            {
                $retreat = $borders[rand(0, count($borders) - 1)];
                $army->move($retreat['gameArea']['id'], $retreat['distance'] / army::speed($army->id) * 3600 + $gameDate, 'retreat');

                return 'retreat';
            }
            else
            {
                $army->disband();

                $history = gameHistory::create(array(
                    'faction_id' => $army->faction_id,
                    'game_id' => $battle->game_id,
                    'history_name' => 'army_combat_annihilated',
                    'history_values' => array(
                        'army_name' => $army->name,
                        'army_id' => $army->id
                    )
                ));

                return 'disband';
            }
        }
    }

    public function conclude($battle)
    {
        $target = gameArea::find($battle->game_area_id, array(
            'with' => array('area', 'faction')
        ));

        if ($battle->result == 'Attacker')
        {
            $attackers = battleArmy::find('all', array(
                'with' => array('army' => array('armyAction', 'gameArea')),
                'conditions' => array('br.side' => 'attacker', 'br.left_time IS NULL')
            ));
            
            foreach ($attackers as $attacker)
            {
                if ($attacker->army['armyAction']['action'] == 'support_attack')
                {
                    continue;
                }
                
                $border = border::findByArea_1_id($attacker->army['gameArea']['area_id'], array(
                    'return' => 'array',
                    'single' => true,
                    'with' => false,
                    'conditions' => array('area_2_id' => $target->area['id'])
                ));
                
                $activeGame = game::find($target->game_id, array(
                    'with' => false,
                    'conditions' => array('g.active' => 1),
                    'return' => 'array',
                    'fields' => array('game_date')
                ));

                $armyAction = armyAction::toItem($attacker->army['armyAction']);
                $armyAction->action_date = $border['distance'] / army::speed($attacker->army['id']) * 3600 + strtotime($activeGame['game_date']);
                $armyAction->save();
            }
        }
        
        battleArmy::update(array('left_time' => time()), array(
            'conditions' => array('battle_id' => $battle->id, 'left_time IS NULL')
        ));

        $users = battleArmy::find('distinct', array(
            'with' => array('army'),
            'return' => 'array',
            'fields' => array('ar.faction_id', 'br.side'),
            'conditions' => array('br.battle_id' => $battle->id)
        ));

        foreach ($users as $user)
        {
            if ($user['side'] == 'attacker')
            {
                $name = ($battle->result == 'Attacker') ? 'army_combat_win' : 'army_combat_loss';

                $history = gameHistory::create(array(
                    'faction_id' => $user['faction_id'],
                    'game_id' => $battle->game_id,
                    'history_name' => $name,
                    'history_values' => array(
                        'faction_name' => $target->faction['name'],
                        'area_name' => $target->area['name'],
                        'area_id' => $target->area['id'],
                        'faction_id' => $target->faction['id'],
                        'battle_id' => $battle->id
                     )
                ));
            }
            else
            {
                $name = ($battle->result == 'Defender') ? 'army_combat_win' : 'army_combat_retreat';
                
                $aggressor = battleArmy::find('first', array(
                    'with' => array('army' => array('faction')),
                    'return' => 'array',
                    'fields' => array('br.id', 'ar.id', 'f.id', 'f.name'),
                    'conditions' => array('br.battle_id' => $battle->id, 'br.side' => 'attacker')
                ));

                $history = gameHistory::create(array(
                    'faction_id' => $user['faction_id'],
                    'game_id' => $battle->game_id,
                    'history_name' => $name,
                    'history_values' => array(
                        'faction_name' => $aggressor['army']['faction']['name'],
                        'area_name' => $target->area['name'],
                        'area_id' => $target->area['id'],
                        'faction_id' => $aggressor['army']['faction']['id'],
                        'battle_id' => $battle->id
                    )
                ));
            }
        }
    }

    public static function doBattle($gameId = null)
    {
        $conditions = array("ba.result != 'Attacker'", "ba.result != 'Defender'");
        
        if ($gameId)
        {
            $conditions[] = 'ba.game_id = ' . $gameId;
        }
        
        $battles = static::find('all', array(
            'with' => false,
            'conditions' => $conditions
        ));

        if (! $battles)
        {
            return;
        }

        $unitEffectiveness = function($units, $defender = false)
        {
            $base = ($defender === true) ? 150 : 100;

            return ($base - (1 - (pow(0.9, ($units - 12))))) / 100;
        };

        foreach ($battles as $battle)
        {
            if ($battle->result == 'Starting')
            {
                $battle->result = 'InProgress';
                $battle->save();

                $target = gameArea::find($battle->game_area_id, array(
                    'return' => 'array', 
                    'with' => array('area')
                ));
                
                $armies = array();

                $users = battleArmy::find('all', array(
                    'with' => array('army'),
                    'return' => 'array',
                    'fields' => array('br.id', 'br.side', 'ar.id', 'ar.faction_id'),
                    'conditions' => array('br.battle_id' => $battle->id)
                ));

                foreach ($users as $user)
                {
                    if (! isset($armies[$user['army']['faction_id']]))
                    {
                        $armies[$user['army']['faction_id']] = array();
                    }

                    $armies[$user['army']['faction_id']][] = $user['army']['id'];
                }
                
                $used = array();

                foreach ($users as $user)
                {
                    if (isset($used[$user['army']['faction_id']]))
                    {
                        continue;
                    }
                    
                    if ($user['side'] == 'attacker')
                    {
                        $history = gameHistory::create(array(
                            'faction_id' => $user['army']['faction_id'],
                            'game_id' => $battle->game_id,
                            'history_name' => 'army_combat_attack',
                            'history_values' => array(
                                'area_name' => $target['area']['name'],
                                'armies' => $armies[$user['army']['faction_id']]
                             )
                        ));
                    }
                    else
                    {
                        $history = gameHistory::create(array(
                            'faction_id' => $user['army']['faction_id'],
                            'game_id' => $battle->game_id,
                            'history_name' => 'army_combat_defend',
                            'history_values' => array(
                                'area_name' => $target['area']['name'],
                                'armies' => $armies[$user['army']['faction_id']]
                            )
                        ));
                    }
                    
                    $used[$user['army']['faction_id']] = true;
                }
            }
            
            $armies = battleArmy::getArmies($battle->id);

            $rounds = $phase = $attackingStrength = $defendingStrength = $attackingOrganisation = $defendingOrganisation = $attackingCasualties = $defendingCasualties = 0;

            $attacker = $defender = $defended = $unitKeys = $armyKeys = $unitArmies = array();

            foreach ($armies['attacker'] as $id => $army)
            {
                foreach ($army['gameUnit'] as $key => $unit)
                {
                    $unit['faction_id'] = $army['faction_id'];
                    $unit['defence'] = $unit['unit']['toughness'];
                    $unit['defended'] = 0;
                    $unit['effectiveness'] = $unitEffectiveness($key);
                    $unit['unit']['soft_attack'] = floor($unit['unit']['soft_attack'] * $unit['effectiveness']);
                    $unit['unit']['hard_attack'] = floor($unit['unit']['hard_attack'] * $unit['effectiveness']);

                    $rounds = ($unit['unit']['soft_attack'] > $rounds) ? $unit['unit']['soft_attack'] : $rounds;
                    $rounds = ($unit['unit']['hard_attack'] > $rounds) ? $unit['unit']['hard_attack'] : $rounds;

                    $attackingStrength += $unit['strength'];
                    $attackingOrganisation += $unit['organisation'];

                    $attacker[] = gameUnit::toItem($unit);
                    $unitArmies['attacker'][$army['id']][] = count($attacker) - 1;

                    $unitKeys[$unit['id']] = $key;
                }

                $armyKeys[$army['id']] = $id;
            }

            foreach ($armies['defender'] as $id => $army)
            {
                foreach ($army['gameUnit'] as $key => $unit)
                {
                    $unit['faction_id'] = $army['faction_id'];
                    $unit['defence'] = $unit['unit']['defensiveness'];
                    $unit['defended'] = 0;
                    $unit['effectiveness'] = $unitEffectiveness($key, true);
                    $unit['unit']['soft_attack'] = floor($unit['unit']['soft_attack'] * $unit['effectiveness']);
                    $unit['unit']['hard_attack'] = floor($unit['unit']['hard_attack'] * $unit['effectiveness']);

                    $rounds = ($unit['unit']['soft_attack'] > $rounds) ? $unit['unit']['soft_attack'] : $rounds;
                    $rounds = ($unit['unit']['hard_attack'] > $rounds) ? $unit['unit']['hard_attack'] : $rounds;

                    $defendingStrength += $unit['strength'];
                    $defendingOrganisation += $unit['organisation'];

                    $defender[] = gameUnit::toItem($unit);
                    $unitArmies['defender'][$army['id']][] = count($defender) - 1;

                    $unitKeys[$unit['id']] = $key;
                }

                $armyKeys[$army['id']] = $id;
            }

            $attackerStartingStrength = $attackingStrength;
            $defenderStartingStrength = $defendingStrength;
            $casualties = array('attacker' => 0, 'defender' => 0);

            $log = "BATTLE: {$battle->id}\n";
            $log .= "Rounds: {$rounds}\n\n";

            while (($battle->result == 'InProgress') && ($phase <= 20))
            {
                $phase ++;

                $log .= "\n\nPhase {$phase}\n";
                $log .= "Attacker: {$attackingStrength} ({$attackingOrganisation})\n";
                $log .= "Defender: {$defendingStrength} ({$defendingOrganisation})\n";

                for ($round = 1; $round <= $rounds; $round ++)
                {
                    $log .= "\nRound {$round}\n";

                    $battleOrder = rand(0, 1);
                    $orderOfBattle = ($battleOrder == 0) ? array($attacker, $defender) : array($defender, $attacker);

                    $log .= ($battleOrder == 0) ? "Attacker attacks first\n\n" : "Defender attacks first\n\n";

                    foreach ($orderOfBattle as $key => $attackingForce)
                    {
                        $defendingForce = $orderOfBattle[!$key];

                        foreach ($attackingForce as $unit)
                        {
                            if ($unit->organisation < 500)
                            {
                                $log .= "{$unit->id} ({$unit->units['name']}) was too demoralised to attack\n";
                                continue;
                            }

                            $target = $defendingForce[array_rand($defendingForce)];

                            $random = rand(1, 100);
                            $attackType = ($random > $target->unit['softness']) ? 'hard' : 'soft';

                            if ($round > $unit->unit[$attackType.'_attack'])
                            {
                                $log .= "{$unit->id} ({$unit->unit['name']}) has used all {$attackType} attacks\n";
                                continue;
                            }

                            $target->defended ++;
                            $chanceToHit = ($target->defended <= $target->defence) ? 80 : 60;
                            $random = rand(1, 100);

                            if ($random < $chanceToHit)
                            {
                                $log .= "{$target->id} ({$target->unit['name']}) defended against an attack\n";
                                continue;
                            }

                            $strength = rand(1, 2) * 2;
                            $organisation = rand(1, 3) * 10;
                            
                            $casualties[($battleOrder == $key) ? 'defender' : 'attacker'] += $strength;

                            $target->strength -= $strength;
                            $target->organisation -= $organisation;
                            $target->organisation = ($target->organisation > 0) ? $target->organisation : 0;

                            $log .= "{$unit->id} ({$unit->unit['name']}) attacks {$target->id} ({$target->unit['name']}) with a {$attackType} attack, dealing {$strength} ({$organisation}) damage\n";
                        }
                    }
                }

                $currentAttackingStrength = $currentAttackingOrganisation = $currentDefendingStrength = $currentDefendingOrganisation = 0;

                foreach ($unitArmies as $side => $armies)
                {
                    foreach ($armies as $key => $army)
                    {
                        $strength = $armyOrganisation = $armySize = 0;

                        foreach ($army as $id)
                        {
                            $unit = ($side == 'attacker') ? $attacker[$id] : $defender[$id];
                            $unit->defended = 0;
                            
                            if ($unit->strength <= 0)
                            {
                                $unit->strength = 0;
                                $unit->disbanded = 1;
                                $unit->save();

                                if ($side == 'attacker')
                                {
                                    unset($attacker[$id]);
                                }
                                else
                                {
                                    unset($defender[$id]);
                                }
                                
                                continue;
                            }
                            
                            $unit->save();
                            
                            $strength += $unit->strength;

                            if ($side == 'attacker')
                            {
                                $currentAttackingStrength += $unit->strength;
                                $currentAttackingOrganisation += $unit->organisation;
                            }
                            else
                            {
                                $currentDefendingStrength += $unit->strength;
                                $currentDefendingOrganisation += $unit->organisation;
                            }
                            
                            $armyOrganisation += $unit->organisation;
                            $armySize ++;
                        }

                        if ($armyOrganisation / $armySize < 500)
                        {
                            $result = $battle->leave(($side == 'attacker') ? $attacker[$army[0]]->army_id : $defender[$army[0]]->army_id, $side);

                            switch ($result)
                            {
                                case 'disband':
                                    $log .= "Army {$unit->army_id} had no areas to retreat to and surrendered\n";
                                break;
                                case 'retreat':
                                    $log .= "Army {$unit->army_id} was too demoralised and was forced to retreat\n";
                                break;
                                default:
                                    $log .= "Army {$unit->army_id} was too demoralised and was forced to leave the battle\n";
                                break;
                            }
                            
                            if ($result == 'disband')
                            {
                                $casualties[$side] += $strength;
                            }

                            foreach ($army as $id)
                            {
                                if ($side == 'attacker')
                                {
                                    unset($attacker[$id]);
                                }
                                else
                                {
                                    unset($defender[$id]);
                                }
                            }

                            unset($unitArmies[$side][$key]);
                        }
                    }
                }

                $strengthDifference = $attackingStrength - $currentAttackingStrength;
                $attackingStrength = $currentAttackingStrength;
                $organisationDifference = $attackingOrganisation - $currentAttackingOrganisation;
                $attackingOrganisation = $currentAttackingOrganisation;
                $attackingCasualties += $strengthDifference;

                $log .= "\n\nAttacker lost {$strengthDifference} strength and {$organisationDifference} organisation during this phase\n";
                $log .= "Attacker strength {$attackingStrength} and organisation {$attackingOrganisation}\n";

                $strengthDifference = $defendingStrength - $currentDefendingStrength;
                $defendingStrength = $currentDefendingStrength;
                $organisationDifference = $defendingOrganisation - $currentDefendingOrganisation;
                $defendingOrganisation = $currentDefendingOrganisation;
                $defendingCasualties += $strengthDifference;

                $log .= "Defender lost {$strengthDifference} strength and {$organisationDifference} organisation during this phase\n";
                $log .= "Defender strength {$defendingStrength} and organisation {$defendingOrganisation}\n";

                if (empty($attacker))
                {
                    $log .= "\n\n\nDefender wins";
                }
                elseif (empty($defender))
                {
                    $log .= "\n\n\nAttacker wins";
                }
                else
                {
                    $battle->current_progress = round(self::calculateProgress($attackingOrganisation, count($attacker), $defendingOrganisation, count($defender)), 2);
                }
            }

            $battle->attacking_casualties += $casualties['attacker'];
            $battle->defending_casualties += $casualties['defender'];
            $battle->save();

            $log .= "\n\nAttacker casualties: {$attackingCasualties}\n";
            $log .= "Defender casualties: {$defendingCasualties}\n";
        }

        //debug($log);
    }

    protected static function calculateProgress($attackingOrganisation, $attackingUnits, $defendingOrganisation, $defendingUnits)
    {
        $attackingRatio = (1 - ($attackingOrganisation / ($attackingUnits * 10000))) * 50;
        $defendingRatio = (1 - ($defendingOrganisation / ($defendingUnits * 10000))) * 50;

        return ($attackingRatio > $defendingRatio) ? 50 - $attackingRatio : 50 + $defendingRatio;
    }
}

?>