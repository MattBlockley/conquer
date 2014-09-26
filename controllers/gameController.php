<?php

namespace app\controllers;

use app\models\armyAction;
use app\models\battle;
use app\models\battleArmy;
use app\models\faction;
use app\models\game;
use app\models\gameArea;
use app\models\gameHistory;
use app\models\region;
use app\models\structure;
use app\models\theatre;
use app\models\theatreFaction;
use app\models\user;
use app\models\unit;
use app\models\userGame;
use core\utility\session;

class gameController extends appController
{
    protected $name = 'game';

    protected function init()
    {
        parent::init();
    
    }

    public function index()
    {
        $pageTitle = 'Introduction';

        $gameInformation = userGame::find('first', array(
            'return' => 'array',
            'fields' => array('ug.id', 'g.id', 'g.theatre_id'),
            'with' => array('game'),
            'conditions' => array('g.active' => 1, 'ug.user_id' => session::read('user')->id, 'ug.status' => 'active')
        ));

        $this->assign(compact('pageTitle', 'gameInformation'));
    }

    public function create()
    {
        $pageTitle = 'Create Game';
        
        if ($this->request->post())
        {
            $theatreId = (int)$this->request->post('theatreId');
            $factionId = (int)$this->request->post('factionId');
            
            $theatre = theatre::find($theatreId, array(
                'return' => 'array', 
                'with' => false
            ));
            
            if ($theatre)
            {
                $faction = faction::find($factionId, array(
                    'return' => 'array',
                    'with' => array('theatreFaction'),
                    'fields' => array('f.id', 'tf.id'),
                    'conditions' => array('tf.theatre_id' => $theatreId, 'tf.faction_id' => $factionId)
                ));

                if ($faction)
                {
                    $game = game::createGame($theatreId, $factionId);
                    
                    $gameInformation = userGame::find('first', array(
                        'return' => 'array',
                        'fields' => array('ug.id', 'ug.faction_id', 'g.id', 'g.theatre_id', 'g.game_date', 'g.paused'),
                        'with' => array('game'),
                        'conditions' => array('g.active' => 1, 'ug.user_id' => session::read('user')->id)
                    ));

                    session::write('gameInformation', $gameInformation);
                    
                    $this->redirect('game/view');
                }
            }
        }
        
        $activeTheatres = theatre::findByActive(1, array(
            'return' => 'array',
            'fields' => array('t.id', 't.name', 'tf.id', 'f.id', 'f.name'),
            'with' => array('theatreFaction' => array('faction'))
        ));

        $this->assign(compact('pageTitle', 'activeTheatres'));
    }
    
    public function run($gameId)
    {
        game::run(array($gameId));
    }
    
    public function join()
    {
        $pageTitle = 'Join Game';
        
        if ($this->request->post())
        {
            $theatreId = (int)$this->request->post('theatreId');
            $factionId = (int)$this->request->post('factionId');
            
            if ($theatreId == 0 && $factionId == 0)
            {
                $game = game::find('first', array(
                    'conditions' => array('g.active' => 1, 'ug.status' => 'open', 'ug.user_id' => 0),
                    'with' => array('userGame')
                ));
            }
            elseif ($theatreId > 0 && $factionId == 0)
            {
                $game = game::find('first', array(
                    'conditions' => array('g.active' => 1, 'ug.status' => 'open', 'ug.user_id' => 0, 'g.theatre_id' => $theatreId),
                    'with' => array('userGame')
                ));
            }
            elseif ($theatreId > 0 && $factionId > 0)
            {
                $game = game::find('first', array(
                    'conditions' => array('g.active' => 1, 'ug.status' => 'open', 'ug.user_id' => 0, 'g.theatre_id' => $theatreId, 'ug.faction_id' => $factionId),
                    'with' => array('userGame')
                ));
            }

            if (isset($game))
            {
                $userGame = userGame::toItem($game->userGame[0]);
                $userGame->user_id = session::read('user')->id;
                $userGame->status = 'active';
                $userGame->save();
                
                $gameInformation = userGame::find('first', array(
                    'return' => 'array',
                    'fields' => array('ug.id', 'ug.faction_id', 'g.id', 'g.theatre_id', 'g.game_date', 'g.paused'),
                    'with' => array('game'),
                    'conditions' => array('g.active' => 1, 'ug.user_id' => session::read('user')->id)
                ));

                $opponent = userGame::find('first', array(
                    'return' => 'array',
                    'fields' => 'ug.faction_id',
                    'conditions' => array('ug.user_id != '.session::read('user')->id, 'ug.game_id' => $gameInformation['game']['id']),
                    'with' => false
                ));

                $faction = faction::find($gameInformation['faction_id'], array(
                    'return' => 'array',
                    'with' => false
                ));
                
                $history = gameHistory::create(array(
                    'faction_id' => $opponent['faction_id'],
                    'game_id' => $gameInformation['game']['id'],
                    'history_name' => 'game_user_joined',
                    'history_values' => array(
                        'user_name' => session::read('user')->handle,
                        'faction_name' => $faction['abbreviation'],
                        'user_id' => session::read('user')->id
                     )
                ));

                session::write('gameInformation', $gameInformation);

                $this->redirect('game/view');
            }
        }
        
        $activeTheatres = theatre::findByActive(1, array(
            'return' => 'array',
            'fields' => array('t.id', 't.name', 'tf.id', 'f.id', 'f.name'),
            'with' => array('theatreFaction' => array('faction'))
        ));

        $this->assign(compact('pageTitle', 'activeTheatres'));
    }
    
    public function quit()
    {
        $userGame = userGame::find('first', array(
            'fields' => array('ug.*', 'g.id', 'g.active', 'g.end_time'),
            'with' => array('game'),
            'conditions' => array('g.active' => 1, 'ug.user_id' => session::read('user')->id, 'ug.status' => 'active')
        ));

        if (! $userGame)
        {
            $this->redirect('game');

            exit;
        }
        
        $userGame->status = 'left';
        $userGame->save();
        
        $opponent = userGame::find('first', array(
            'return' => 'array',
            'fields' => 'ug.faction_id',
            'conditions' => array('ug.user_id != '.session::read('user')->id, 'ug.game_id' => $userGame->game['id']),
            'with' => false
        ));
        
        $faction = faction::find($userGame->faction_id, array(
            'return' => 'array',
            'with' => false
        ));

        $history = gameHistory::create(array(
            'faction_id' => $opponent['faction_id'],
            'game_id' => $userGame->game['id'],
            'history_name' => 'game_user_quit',
            'history_values' => array(
                'user_name' => session::read('user')->handle,
                'faction_name' => $faction['abbreviation']
             )
        ));
        
        $activeUsers = userGame::find('all', array(
            'with' => array('game'),
            'conditions' => array('ug.status' => 'active', 'ug.game_id' => $userGame->game['id'])
        ));
        
        if (count($activeUsers) == 1)
        {
            $activeUsers[0]->status = 'won';
            $activeUsers[0]->save();
            
            $game = game::toItem($userGame->game);
            $game->finish();
        }
        
        session::delete('gameInformation');
        
        $this->redirect('game');

        exit;
    }

    public function view()
    {
        $userGame = userGame::find('first', array(
            'return' => 'array',
            'fields' => array('ug.id', 'ug.faction_id', 'ug.money', 'ug.supplies', 'ug.fuel', 'ug.manpower', 'g.id', 'g.paused', 'g.game_date'),
            'with' => array('game'),
            'conditions' => array('g.active' => 1, 'ug.user_id' => session::read('user')->id)
        ));

        if (! isset($userGame['id']))
        {
            $this->redirect('game');

            exit;
        }

        $mapInformation = gameArea::find('all', array(
            'return' => 'array',
            'conditions' => array('ga.game_id' => $userGame['game']['id']),
            'fields' => array('ga.*', 'a.id', 'a.*'),
            'with' => array('area'),
            'order' => array('a.name')
        ));
        
        $opponent = userGame::find('first', array(
            'return' => 'array',
            'fields' => 'ug.user_id',
            'conditions' => array('ug.user_id != '.session::read('user')->id, 'ug.game_id' => $userGame['game']['id']),
            'with' => false
        ));

        $gameInformation = session::read('gameInformation');

        $overview = gameArea::overview($gameInformation['game']['id'], $gameInformation['faction_id']);

        $histories = gameHistory::findByGame($gameInformation['game']['id'], $gameInformation['faction_id']); 

        $this->assign(compact('userGame', 'gameInformation', 'mapInformation', 'histories', 'overview', 'opponent'));

        $this->render['layout'] = 'game';
    }

    public function update()
    {
        $this->render = array(
            'layout' => false,
            'template' => false
        );

        $gameInformation = session::read('gameInformation');
        $histories = gameHistory::findByGame($gameInformation['game']['id'], $gameInformation['faction_id'], true);

        if ($histories)
        {
            foreach ($histories as &$history)
            {
                $history['type'] = $history['history']['name'];
                $history['history_date'] = date('H:i j F, Y', strtotime($history['history_date']));
                unset($history['history']);

                foreach ($history['gameHistoryValue'] as $value)
                {
                    if (isset($history[$value['field']]))
                    {
                        if (is_array($history[$value['field']]))
                        {
                            $history[$value['field']][] = $value['value'];
                        }
                        else
                        {
                            $history[$value['field']] = array($history[$value['field']], $value['value']);
                        }
                    }
                    else
                    {
                        $history[$value['field']] = $value['value'];
                    }
                }

                unset($history['gameHistoryValue']);
            }
            
            unset($history);
        } 
        
        $userGame = userGame::find('first', array(
            'return' => 'array',
            'fields' => array('ug.money', 'ug.supplies', 'ug.fuel', 'ug.manpower', 'g.game_date', 'g.paused', 'g.active'),
            'with' => array('game'),
            'conditions' => array('ug.user_id' => session::read('user')->id)
        ));
        
        $userGame['active'] = $userGame['game']['active'];
        $userGame['paused'] = $userGame['game']['paused'];
        $userGame['game_date'] = $userGame['game']['game_date'];
        $userGame['game_date_display'] = date('H:i j F, Y', strtotime($userGame['game_date']));
        unset($userGame['game']);
        
        $overview = gameArea::overview($gameInformation['game']['id'], $gameInformation['faction_id']);
        
        $battles = array();
        
        if ($userGame['paused'] == 0)
        {        
            $activeBattles = battle::findByGame_id($gameInformation['game']['id'], array(
                'return' => 'array', 
                'with' => array('gameArea' => array('area')), 
                'conditions' => array('ba.result' => 'InProgress')
            ));

            if ($activeBattles)
            {
                foreach ($activeBattles as $battle)
                {
                    $data = battleArmy::getArmies($battle['id']);
                    $attacker = $defender = 0;
                    $ids = array();
                    $side = null;

                    foreach ($data['attacker'] as $army)
                    {
                        $attacker += count($army['gameUnit']);

                        if ($army['faction_id'] == $gameInformation['faction_id'])
                        {
                            $ids[] = $army['id'];
                            $side = 'attacker';
                        }
                    }

                    foreach ($data['defender'] as $army)
                    {
                        $defender += count($army['gameUnit']);

                        if ($army['faction_id'] == $gameInformation['faction_id'])
                        {
                            $ids[] = $army['id'];
                            $side = 'defender';
                        }
                    }

                    if ($side)
                    {
                        $battles[] = array(
                            'id' => $battle['id'], 
                            'area_id' => $battle['gameArea']['area_id'],
                            'side' => $side, 
                            'progress' => $battle['current_progress'], 
                            'attacker' => $attacker, 
                            'defender' => $defender, 
                            'armies' => $ids
                        );
                    }
                }
            }
        }

        $this->render['data'] = array('histories' => $histories, 'overview' => $overview, 'battles' => $battles, 'game' => $userGame);
    }
    
    public function session()
    {
        debug(session::read('gameInformation')); exit;
    }
    
    public function start()
    {
        $this->render['template'] = false;
        $this->render['data'] = array('token' => session::read('token'));
        
        $gameInformation = session::read('gameInformation');
        
        if (! $gameInformation)
        {
            return;
        }
        
        $game = game::find($gameInformation['game']['id'], array(
            'with' => false,
            'conditions' => array('g.paused' => 1)
        ));
        
        if (! $game)
        {
            return;
        }
        
        file_put_contents(APP . '/resources/tmp/cache/games/' . $gameInformation['game']['id'], 1);
        
        if (substr(php_uname(), 0, 7) == 'Windows')
        { 
            popen('start /B "" "D:\Program Files\PHP\php.exe" "D:\Program Files\Apache 2.4\htdocs\conquer\app\extensions\command.php" -f game -a ' . $gameInformation['game']['id'] , "r");
        } 
        else
        { 
            exec('php /home/effigygame/effi.gy/conquer/app/extensions/command.php -f game -a ' . $gameInformation['game']['id'] . ' > /dev/null &');   
        }
        
        $game->paused = 0;
        $game->save();
    }
    
    public function pause()
    {
        $this->render['template'] = false;
        $this->render['data'] = array('token' => session::read('token'));
        
        $gameInformation = session::read('gameInformation');
        
        if (! $gameInformation)
        {
            return;
        }
        
        $game = game::find($gameInformation['game']['id'], array(
            'with' => false,
            'conditions' => array('g.paused' => 0)
        ));
        
        if (! $game)
        {
            return;
        }
        
        unlink(APP . '/resources/tmp/cache/games/' . $gameInformation['game']['id']);
        
        $game->paused = 1;
        $game->save();
    }
}

?>