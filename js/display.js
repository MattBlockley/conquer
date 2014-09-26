$(function() {
    var base = window.location.href.replace('game/view', ''),
        now = Math.ceil((gameDate.getTime() + gameDate.getTimezoneOffset() * 60000) / 1000),
        previousItem = null,
        token = null,
        scroll = null,
        areaInformation = $('#area-information')
        currentMenu = 'area',
        currentAction = 'summary',
        currentId = null,
        gameInterval = null,
        areaInterval = null,
        battleInterval = null,
        updateInterval = null,
        date = new Date,
        historyContainer = null,
        historyStatus = true,
        historyToggle = null,
        statusContainer = null,
        selectorObject = $('#selector'),
        statusList = $('#status-list'),
        armyCounters = $('.army-counter'),
        selector = [0,0],
        armySection = [],
        contextMenu = $('#context'),
        statusButtons = $('#status-menu li'),
        money = $('#money'),
        supplies = $('#supplies'),
        fuel = $('#fuel'),
        manpower = $('#manpower'),
        power = $('#power'),
        areas = $('#areas'),
        armies = $('#armies'),
        airforces = $('#airforces'),
        timer = $('#timer');

        historyToggle = $('#history-toggle'),
        token = $('#token'),
        historyContainer = $('#history-container'),
        mapContainer = $('#map-container'),
        loadingProgressBar = $('#loading-container .progress-bar');

    $.ajaxSetup({
        cache: false,
        beforeSend: function(xhr, settings) {
            if (! active && settings.url.indexOf('game/') == -1 && settings.url.indexOf('chat/') == -1) {
                return false;
            }
        }
    });

    if (activeChat) {
        chat.setup();
    }

    $('#close-info').on('click', function(event) {
        $('#info-container').hide();
    });

    $('.game-status').on('click', function(event) {
        var $this = $(this);

        if ($this.attr('data-status') == 'start') {
            $.post(base + 'game/start.json', function(data) {
                token.attr('value', data.token);
                $this.text('Pause Game').attr('data-status', 'pause');
                active = true;
            });
        } else if ($this.attr('data-status') == 'pause') {
            $.post(base + 'game/pause.json', function(data) {
                token.attr('value', data.token);
                $this.text('Start Game').attr('data-status', 'start');
            });
        }

        return false;
    });

    statusButtons.on('click', function(event) {
        if (event.target.id == currentMenu && currentAction == 'summary') {
            return;
        }

        setMenu(event.target.id, 'summary');

        if (currentMenu == 'area') {
            areaSummary();
        } else if (currentMenu == 'army') {
            armySummary();
        } else if (currentMenu == 'airforce') {
            airforceSummary();
        } else if (currentMenu == 'combat') {
            battleSummary();
        }
    });

    function areaSummary() {
        $.get(base + 'area/summary', function(data) {
            setStatusContent(data);
        });
    }

    function armySummary() {
        $.get(base + 'army/summary', function(data) {
            setStatusContent(data);
        });
    }

    function airforceSummary() {
        $.get(base + 'army/summary', function(data) {
            setStatusContent(data);
        });
    }

    function battleSummary() {
        $.get(base + 'battle/summary', function(data) {
            setStatusContent(data);
        });
    }

    mapContainer.on('contextmenu', function(event) {
        return false;
    });

    mapContainer.on('mousedown', function(event) {
        if (event.which == 2 || event.which == 3) {
            return;
        }

        contextMenu.hide();

        selector[0] = event.pageX;
        selector[1] = event.pageY;
        selectorObject.css('left', selector[0]);
        selectorObject.css('top', selector[1]);
        event.preventDefault();
        event.stopPropagation();

        $('body').bind('mousemove', selectorDrag)
            .bind('mouseup', selectorEnd);
    });

    function selectorDrag(event) {
        var width = event.pageX - selector[0],
            height = event.pageY - selector[1];

        if (width > 0) {
            selectorObject.css('width', width);
        } else {
            selectorObject.css('left', event.pageX);
            selectorObject.css('width', -width);
        }

        if (height > 0) {
            selectorObject.css('height', height);
        } else {
            selectorObject.css('top', event.pageY);
            selectorObject.css('height', -height);
        }

        if (width != 0 && height != 0) {
            selectorObject.show();
        }

        event.preventDefault();
        event.stopPropagation();
    }

    function selectorEnd(event) {
        $('body').unbind('mousemove', selectorDrag)
            .unbind('mouseup', selectorEnd);

        var position = selectorObject.position(),
            startX = position.left,
            startY = position.top,
            endX = startX + selectorObject.width(),
            endY = startY + selectorObject.height(),
            selection = [];

        armyCounters.each(function(index, item) {
            $item = $(item);

            if ($item.hasClass('hidden')) {
                return;
            }

            var offset = $item.offset();

            if (endX > offset.left + 11 && offset.left > startX && endY > offset.top + 11 && offset.top > startY) {
                if (armyLocations[$item.data('area-id')]) {
                    selection.push(armyLocations[$item.data('area-id')]);
                }
            }
        });

        if (selection.length > 0) {
            currentId = null;
            selection = Array.prototype.concat.apply([], selection);

            if (event.shiftKey) {
                selectedArmies = Array.prototype.concat.apply(selectedArmies, selection);
            } else {
                selectedArmies = selection;
            }

            armySelection();
        }

        selectorObject.css({'width' : 0, 'height' : 0}).hide();
    }

    mapContainer.on('mousemove', function(event) {
        areaInformation.css('left', event.pageX + 12 + 'px').css('top', event.pageY + 12 + 'px')
    });

    $('#map-container area').each(function() {
        var mapArea = $(this);

        mapArea.on('mousemove', function(event) {
            if (selectorObject.css('display') == 'none') {
                if (areaInterval != null) {
                    window.clearTimeout(areaInterval);
                }

                areaInformation.css('top', event.pageY + 10 + 'px')
                    .html(areaNames[mapArea.data('area-id')])
                    .show();
            }
        }).on('mouseleave', function(event) {
            areaInterval = window.setTimeout(function() {
                areaInformation.hide();
            }, 500);
        }).on('click', function(event) {
            displayArea(mapArea.data('area-id'));
        }).on('contextmenu', function(event) {
            var target = $(event.target).eq(0).data('area-id'),
                armies = $('.status-army-container'),
                action = (currentMenu == 'army' && currentAction == 'selection') ? selectedArmies.join('/') :
                    ((currentMenu == 'army' && currentAction == 'summary') ? 'summary' : ((currentId) ? currentId : 0));

            $.get(base + 'area/context/' + target + '/' + currentMenu + '/' + action, function(data) {
                var offset = mapContainer.offset(),
                    x = event.pageX,
                    y = event.pageY,
                    height = 0;

                contextMenu.html(data).show();

                contextMenu.find('ul li ul').each(function() {
                    height = ($(this).innerHeight() > height) ? $(this).innerHeight() : height;
                });

                contextMenu.removeClass('bottom').removeClass('left');

                if (x + 403 - offset.left > mapContainer.width()) {
                    contextMenu.addClass('left');
                }

                if (x + 201 - offset.left > mapContainer.width()) {
                    x = mapContainer.width() + offset.left - 201;
                }

                if (y + height > mapContainer.height()) {
                    contextMenu.addClass('bottom');
                }

                contextMenu.css({
                    left: x,
                    top: y
                }).show();
            });
        });
    });

    contextMenu.on('click', 'a', function(event) {
        var $this = $(this),
            type = $this.data('type'),
            id = $this.data('id'),
            areaId = $this.parents('.context-menu-top').data('area-id');

        if (! type) {
            return;
        }

        if (type == 'close') {
            contextMenu.hide();
        } else if (type == 'structure' && id) {
            var params = 'token=' + $('#token').attr('value') + '&structureId=' + id;

            $.post(base + 'structure/construct/' + areaId + '.json', params, function(data) {
                token.attr('value', data.token);

                if (currentMenu == 'area' && currentAction == 'view' && areaId == currentId) {
                    getAreaData(currentId);
                }
            });

            contextMenu.hide();
        } else if (type == 'unit' && id) {
            var params = 'token=' + $('#token').attr('value') + '&unitId=' + id;

            $.post(base + 'unit/train/' + areaId + '.json', params, function(data) {
                token.attr('value', data.token);

                if (currentMenu == 'area' && currentAction == 'view' && areaId == currentId) {
                    getAreaData(currentId);
                }
            });

            contextMenu.hide();
        } else if (type == 'attack' || type == 'move' || type == 'retreat' || type == 'redeployment' || type == 'reserve' || type == 'support_attack' || type == 'cancel') {
            doArmyActions($('.status-army-container'), [areaId, areaNames[areaId]], type);

            contextMenu.hide();
        } else if (type == 'merge') {
            var id = (currentMenu == 'army' && currentAction == 'view') ? currentId : 'first',
                params = 'token=' + $('#token').attr('value'),
                merges = [];

            $('.status-army-container').each(function() {
                params += '&merges[]=' + $(this).data('army-id');
            });

            $.post(base + 'army/merge/' + id + '.json', params, function(data) {
                token.attr('value', data.token);

                if (currentMenu == 'area' && currentAction == 'view') {
                    getAreaData(currentId);
                } else if (currentMenu == 'army' && currentAction == 'view') {
                    displayArmy(currentId);
                } else if (currentMenu == 'army' && currentAction == 'summary') {
                    armySummary();
                } else if (currentMenu == 'army' && currentAction == 'selection') {
                    armySelection();
                }
            });

            contextMenu.hide();
        }
    });

    historyToggle.on('click', function(event) {
        historyStatus = !historyStatus;

        if (historyStatus) {
            historyContainer.find('div.history-entry').show();
            historyContainer.css('height', '100px');
            historyToggle.removeClass('history-toggle-collapse').addClass('history-toggle');

            $(this).children('img').attr('src', base + 'img/icons/down.png');
        } else {
            historyContainer.css('height', '20px');
            historyContainer.find('div.history-entry').hide();
            historyToggle.removeClass('history-toggle').addClass('history-toggle-collapse');

            $(this).children('img').attr('src', base + 'img/icons/up.png');
        }
    });

    $('#status-list').on('click', '.area-link', function(event) {
        displayArea($(this).data('area-id'));

        return false;
    }).on('click', '.army-link', function(event) {
        var $this = $(this);

        if (event.shiftKey && selectedArmies) {
            var parent = $this.parents('.status-army-container');

            for (k in selectedArmies) {
                if (selectedArmies[k] == $this.data('army-id')) {
                    delete selectedArmies[k];

                    selectedArmies = selectedArmies.filter(function() {
                        return true;
                    });
                }
            }

            if (parent.siblings('.status-army-container').length == 0) {
                parent.parent().remove();
            } else {
                parent.remove();
            }

            return false;
        }

        displayArmy($this.data('army-id'));

        return false;
    }).on('click', '.structure-link', function(event) {
        var link = $(this);

        displayStructure(link.data('structure-id'), link.data('area-id'));

        return false;
    }).on('click', '.unit-link', function(event) {
        var link = $(this);

        displayUnit(link.data('unit-id'), link.data('army-id'));

        return false;
    }).on('click', '.battle-link', function(event) {
        displayBattle($(this).data('battle-id'));

        return false;
    });

    function setStatusContent(data) {
        if (statusContainer == null) {
            statusContainer = statusList.children('.scroller-inner-container');
        }

        statusContainer.html(data);
        statusScroll.initiate();
    }

    function setOverlayContent(data, width, height, left, top) {
        overlayWrapper.children('.scroller-inner-container').html(data);
        overlayScroll.initiate(width, height, left, top);
    }

    function setMenu(menu, action) {
        $('#' + currentMenu).removeClass('active');
        $('#' + menu).addClass('active');
        currentMenu = menu;
        currentAction = action;
    }

    function armySelection() {
        $.get(base + 'army/summary/' + selectedArmies.join('/'), function(data) {
            setMenu('army', 'selection');
            setStatusContent(data);
        });
    }

    function displayArea(areaId) {
        if (previousItem) {
            previousItem.attr('class', previousItem.attr('class').replace('over', 'owner'));
        }

        var area = $('#area_definition_' + areaId);

        area.attr('class', area.attr('class').replace('owner', 'over'));
        previousItem = area;

        if (currentMenu != 'area' || currentAction != 'view' || currentId != areaId) {
            getAreaData(areaId);
        }
    }

    function displayStructure(structureId, areaId) {
        $.get(base + 'structure/view/' + structureId + '/' + areaId, function(data) {
            setMenu('area', 'structure');
            setStatusContent(data);
        });
    }

    function displayUnit(unitId, armyId) {
        $.get(base + 'unit/view/' + unitId + '/' + armyId, function(data) {
            setMenu('army', 'unit');
            setStatusContent(data);
        });
    }

    function getAreaData(areaId) {
        $.get(base + 'area/view/' + areaId, function(data) {
            setStatusContent(data);
            setMenu('area', 'view');
            currentId = areaId;
            selectedArmies = [];
        });
    }

    function updateGame() {
        $.get(base + 'game/update.json', function(data) {
            money.text('$' + data.game.money + ' (+' + data.overview.money + ')');
            supplies.text(data.game.supplies + ' (' + ((data.overview.supplies > 0) ? '+' : '') + data.overview.supplies + ')');
            fuel.text(data.game.fuel + ' (' + ((data.overview.fuel > 0) ? '+' : '') + data.overview.fuel + ')');
            manpower.text(data.game.manpower + ' (+' + data.overview.manpower + ')');
            power.text(data.overview.power);
            areas.text(data.overview.areas);
            armies.text(data.overview.armies.divisions + ((data.overview.armies.developments > 0) ? ' (+' + data.overview.armies.developments + ')' : ''));
            airforces.text(data.overview.airforces.divisions + ((data.overview.airforces.developments > 0) ? ' (+' + data.overview.airforces.developments + ')' : ''));

            armyCounters.addClass('hidden');
            armyLocations = [];

            gameDate = new Date(data.game.game_date);
            timer.text(data.game.game_date_display);
            now = Math.ceil((gameDate.getTime() + gameDate.getTimezoneOffset() * 60000) / 1000);

            if (active == data.game.paused) {
                if (data.game.paused == 0) {
                    $('.game-status[data-status="start"]').text('Pause Game').attr('data-status', 'pause');
                    active = 1;
                } else if (data.game.paused == 1) {
                    $('.game-status[data-status="pause"]').text('Start Game').attr('data-status', 'start');
                    active = 0;
                }
            }

            if (data.overview.locations) {
                armyCounters.each(function() {
                    var counter = $(this),
                        id = counter.data('area-id');

                    if (data.overview.locations[id]) {
                        counter.removeClass('hidden').children('.army-counter-text').text(data.overview.locations[id].count);
                    }
                });

                $.each(data.overview.locations, function(id, val) {
                    armyLocations[id] = val.ids;
                });
            }

            if (data.battles) {
                var activeBattles = [],
                    activeBattleArmies = [];

                $.each(data.battles, function(index, battle) {
                    var container = $('.battle-container[data-battle-id=' + battle.id + ']'),
                        title = (battle.progress >= 50) ? 'The attacker has made ' + battle.progress + '% progress in defeating the defender' : 'The defender has made ' + (100 - battle.progress) + '% progress in repelling the attacker';

                    activeBattles[battle.id] = 1;
                    activeBattleArmies[battle.id] = [];

                    if (container.length) {
                        container.find('.status-battle-count-left').text(battle.attacker);
                        container.find('.status-battle-count-right').text(battle.defender);
                        container.find('.progress-container').attr('title', title).children('.progress-bar').css('width', battle.progress + '%');
                    } else if (currentMenu == 'area' && currentAction == 'view' && currentId == battle.area_id) {
                        getAreaData(currentId);
                    } else if (currentMenu == 'combat' && currentAction == 'summary') {
                        battleSummary();
                    } else if (currentMenu == 'army' && currentAction == 'view') {
                        for (k in battle.armies) {
                            if (battle.armies[k] == currentId) {
                                displayArmy(currentId);
                                activeBattleArmies[battle.id][currentId] = 1;

                                break;
                            }
                        }
                    }
                });

                var container = $('.battle-container');

                container.each(function() {
                    if (! activeBattles[$(this).data('battle-id')]) {
                        if (currentMenu == 'army' && currentAction == 'view') {
                            displayArmy(currentId);
                        } else if (currentMenu == 'area' && currentAction == 'view') {
                            getAreaData(currentId);
                        } else if (currentMenu == 'combat' && currentAction == 'summary') {
                            battleSummary();
                        }

                        return;
                    } else if (currentMenu == 'army' && currentAction == 'view' && ! activeBattleArmies[$(this).data('battle-id')][currentId]) {
                        displayArmy(currentId);
                    }
                });

            }

            if (data.histories) {
                $.each(data.histories, function(index, historyObject) {
                    switch(historyObject.type) {
                        case 'structure_complete':
                            if (currentMenu == 'area' && currentAction == 'view' && currentId == historyObject.area_id) {
                                getAreaData(currentId);
                            }
                        break;
                        case 'army_unit_complete':
                            if (currentMenu == 'area' && currentAction == 'view' && currentId == historyObject.area_id) {
                                getAreaData(currentId);
                            } else if (currentMenu == 'army' && currentAction == 'summary') {
                                armySummary();
                            }
                        break;
                        case 'their_province_occupation':
                        case 'our_province_occupation':
                            var areaOccupied = $('#area_definition_' + historyObject.area_id),
                                currentClass = areaOccupied.attr('class');

                            if (currentMenu == 'area' && currentAction == 'view' && currentId == historyObject.area_id) {
                                getAreaData(currentId);
                            } else if (currentMenu == 'area' && currentAction == 'summary') {
                                areaSummary();
                            } else if (currentMenu == 'area' && currentAction == 'view') {
                                $('.area-container[data-area-id=' + historyObject.area_id + ']').children('.status-province-list-count').text(historyObject.abbreviation);
                            }

                            areaOccupied.addClass('flash').addClass('area_owner_' + historyObject.faction_id + '_' + historyObject.area_id).removeClass(currentClass);

                            window.setTimeout(function() {
                                areaOccupied.removeClass('flash');
                            }, 500);
                        break;
                        case 'army_arrive':
                        case 'army_combat_win':
                        case 'army_combat_retreat':
                        case 'army_combat_annihilated':
                            if (currentMenu == 'area' && currentAction == 'view' && currentId == historyObject.area_id) {
                                getAreaData(currentId);
                            } else if (currentMenu == 'area' && currentAction == 'view' && currentId == historyObject.previous_area_id) {
                                getAreaData(currentId);
                            } else if (currentMenu == 'army' && currentAction == 'view' && currentId == historyObject.army_id) {
                                if (historyObject.type == 'army_combat_annihilated') {
                                    armySummary();
                                } else {
                                    displayArmy(currentId);
                                }
                            } else if (currentMenu == 'army' && currentAction == 'summary') {
                                armySummary();
                            } else if (currentMenu == 'army' && currentAction == 'selection') {
                                armySelection();
                            } else if (currentMenu == 'combat' && currentAction == 'summary') {
                                container = $('.battle-container[data-battle-id=' + historyObject.battle_id + ']').remove();
                                statusScroll.initiate();
                            }
                        break;
                        case 'game_user_joined':
                            $('.chat-link').attr('href', $('.chat-link').attr('href').slice(0, -1) + historyObject.user_id);
                        break;
                    }

                    style = (historyStatus) ? '' :  'style="display: none;"';
                    historyToggle.after('<div class="history-entry"' + style + '>' + historyObject.history_date + ': ' + historyObject.value + '</div>');
                    historyScroll.initiate();
                });
            }

            if (data.game.active == 0) {
                window.clearInterval(gameInterval);
            }
        });
    }

    function armyEvents() {
        $('#status-list').on('click', '.status-army-action', function(event) {
            $this = $(this);

            if ($this.hasClass('locked')) {
                return;
            }

            $this.hide();
            $this.next('.status-army-actions').show().children('#army-actions').show();
        });

        $('#status-list').on('change', '.status-army-actions select', function(event) {
            var $this = $(this),
                container = $this.parents('.status-army-container'),
                actionName = container.find('.status-army-action'),
                actionList = container.find('.status-army-actions'),
                actionProgress = container.find('.status-army-action-progress'),
                id = container.data('army-id');

            if (this.id == 'army-actions') {
                if (this.value <= 0) {
                    $this.val(-1).parent().hide().prev().show();
                } else if (this.value == 'cancel') {
                    doArmyActions(container, [0, 0], 'cancel');
                } else if (this.value == 'attack' || this.value == 'support_attack') {
                    $this.hide();
                    $this.siblings('#army-movements-friendly').hide();
                    $this.siblings('#army-movements-hostile').show();
                } else if (this.value == 'move' || this.value == 'retreat') {
                    $this.hide();
                    $this.siblings('#army-movements-friendly').show();
                    $this.siblings('#army-movements-hostile').hide();
                } else if (this.value == 'reserve') {
                    var params = 'token=' + $('#token').attr('value') + '&action=reserve&armies[]=' + id;

                    $.post(base + 'army/action.json', params, function(data) {
                        token.attr('value', data.token);

                        if (currentMenu == 'army' && currentAction == 'view') {
                            displayArmy(currentId);
                        } else {
                            $this.val(-1).parent().hide();
                            actionName.text('Reserve').show();
                        }
                    });
                }
            } else if (this.id == 'army-movements-friendly' || this.id == 'army-movements-hostile') {
                if (this.value <= 0) {
                    $this.val(-1).hide().siblings('#army-actions').val(-1).show();
                } else {
                    var option = $this.find('option:selected');

                    doArmyActions(container, [option.val(), option.text()], container.find('#army-actions').val());
                }
            }
        });
    }

    function doArmyActions(containers, target, action) {
        var params = new Array('token=' + $('#token').attr('value'), 'action=' + action, 'target=' + target[0]),
            texts = {
                'cancel' : 'This unit has no mission set',
                'move' : 'Moving to',
                'attack' : 'Attacking',
                'support_attack' : 'Support Attacking',
                'redeployment' : 'Redeploying to',
                'retreat' : 'Retreating to',
                'reserve' : 'Reserve'
            },
            text = texts[action];

        text = (action == 'reserve' || action == 'cancel') ? text : text + ' ' + target[1];

        containers.each(function() {
            params.push('armies[]=' + $(this).data('army-id'));
        });

        $.post(base + 'army/action.json', params.join('&'), function(data) {
            token.attr('value', data.token);

            containers.each(function() {
                var $this = $(this),
                    progress = $this.find('.status-army-action-progress'),
                    arrival = progress.find('.status-army-action-arrival'),
                    container = progress.children('div.progress-container'),
                    bar = container.children('div.progress-bar'),
                    result = data.result[$this.data('army-id')];

                if (! result || result[0] == false) {
                    $this.find('.status-army-action').show();
                    return;
                }

                $this.find('.status-army-actions').hide().find('select').val(-1).hide().parent().hide();
                $this.find('.status-army-action').text(text).show();

                if (result[1]) {
                    bar.attr('data-length', result[1]);
                    bar.attr('data-finish', result[2]);
                    bar.css('width', 0);

                    container.addClass('active');
                    arrival.text('Arrival: ' + result[3]);
                    progress.show();
                } else {
                    container.removeClass('active');
                    progress.hide();
                }
            });

            statusScroll.initiate();
        });
    }

    function displayBattle(id) {
        $.get(base + 'battle/view/' + id, function(data) {
            currentId = id;
            setMenu('combat', 'view');
            setStatusContent(data);
        });
    }

    function structureEvents() {
        $('#status-list').on('click', '#structure_create', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&structureId=' + $('#available_structures').val();

            $.post(base + 'structure/construct/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        }).on('click', '.structure-development-down', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&developmentId=' + $(this).attr('rel') + '&type=down';

            $.post(base + 'structure/priority/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        }).on('click', '.structure-development-up', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&developmentId=' + $(this).attr('rel') + '&type=up';

            $.post(base + 'structure/priority/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        });
    }

    function displayArmy(id) {
        $.get(base + 'army/view/' + id, function(data) {
            currentId = id;
            setStatusContent(data);
            setMenu('army', 'view');
            selectedArmies = [];

            $('#army-merge').on('click', function(event) {
                var params = 'token=' + $('#token').attr('value');
                params += '&merges[]=' + $('#army-merge-list').val();

                $.post(base + 'army/merge/' + id + '.json', params, function(data) {
                    token.attr('value', data.token);
                    displayArmy(id);
                });
            });

            $('.split-army-link').one('click', function(event) {
                $(this).hide();
                $('.split-army-container').show();
                statusScroll.initiate();

                $('#split-army-move-new').on('click', function(event) {
                    $("#split-army-from option:selected").each(function () {
                        $(this).appendTo($('#split-army-to')).removeAttr('selected');
                    });
                });

                $('#split-army-move-old').on('click', function(event) {
                    $("#split-army-to option:selected").each(function () {
                        $(this).appendTo($('#split-army-from')).removeAttr('selected');
                    });
                });

                $('.split-army option').on('dblclick', function(event) {
                    var target = ($(this).parent().attr('name') == 'split-army-from') ? 'split-army-to' : 'split-army-from';
                    $(this).appendTo($('#'+target)).removeAttr('selected');
                });

                $('#split-army').on('click', function(event) {
                    var units = $('#split-army-to option');

                    if (units.length > 0) {
                        var params = new Array('token=' + $('#token').attr('value'));

                        units.each(function() {
                            params.push('units[]=' + this.value);
                        });

                        params = params.join('&');

                        $.post(base + 'army/split/' + id + '.json', params, function(data) {
                            token.attr('value', data.token);
                            displayArmy(id);
                        });
                    }
                });

                return false;
            });

            $('.readonly').on('dblclick', function(event) {
                var $this = $(this);

                $this.removeAttr('readonly').css('cursor', 'auto');
                this.select();

                $this.one('blur', function(event) {
                    $.post(base + 'army/name/' + id + '.json', 'token=' + $('#token').attr('value') + '&name=' + this.value, function(data) {
                        token.attr('value', data.token);
                        $this.attr('readonly', 1);
                        $this.css('cursor', 'pointer');
                    });
                });

                $this.on('keypress', function(event) {
                    var keycode = (event.keyCode) ? event.keyCode : event.which;

                    if (keycode == 13) {
                        $.post(base + 'army/name/' + id + '.json', 'token=' + $('#token').attr('value') + '&name=' + this.value, function(data) {
                            token.attr('value', data.token);
                            $this.attr('readonly', 1);
                            $this.css('cursor', 'pointer');
                        });

                        return false;
                    }

                });
            });
        });
    }

    function updateArmy(id) {
        $.get(base + 'army/update/' + id + '.json', function(data) {
            var activeUnits = [],
                mergeContainer = $('.army-merge'),
                select = $('#army-merge-list');

            $.each(data.units, function(id, unit) {
                activeUnits[id] = true;
                $('.unit-container[data-unit-id=' + id + ']').children('.status-province-list-count').text(unit[0] + ' / ' + unit[1]);
            });

            $('.unit-container').each(function() {
                if (! activeUnits[$(this).data('unit-id')]) {
                    $(this).remove();
                }
            });

            if (data.action) {
                mergeContainer.text('You cannot merge any armies into this army');
            } else {
                if (Object.keys(data.armies).length) {
                    if (select.length) {
                        var selected = 0;

                        select.find('option').each(function() {
                            if (this.selected) {
                                selected = this.value;
                                return;
                            }
                        });

                        select.html('');

                        $.each(data.armies, function(id, value) {
                            select.append($('<option>', { value : value[0] }).text(value[1]));
                        });

                        if (selected) {
                            select.val(selected);
                        }
                    } else {
                        mergeContainer.html('<select name="army-merge-list" id="army-merge-list"><input type="button" name="army-merge" class="action-button" value="Merge" id="army-merge">');
                        select = $('#army-merge-list');

                        $.each(data.armies, function(id, value) {
                            select.append($('<option>', { value : value[0] }).text(value[1]));
                        });
                    }
                } else {
                    mergeContainer.text('There are no other armies in this area to merge with');
                }
            }

            statusScroll.initiate(0, 0, 0, 0, true);
        });
    }

    function updateBattle(id) {
        $.get(base + 'battle/update/' + id + '.json', function(data) {
            if (! data.units) {
                return;
            }

            var attackerContainer = $('.attacker-container'),
                defenderContainer = $('.defender-container'),
                activeUnits = [];

            $.each(data.units.attacker, function(id, unit) {
                var container = $('.battle-unit[data-unit-id=' + unit[0] + ']'),
                    containers = null,
                    base = null,
                    clone = null;

                activeUnits[unit[0]] = true;

                if (! container.length) {
                    base = attackerContainer.find('.battle-unit').eq(0);
                    clone = base.clone();

                    clone.attr('data-unit-id', unit[0]).find('a.army-link').attr('data-army-id', unit[4]).text(unit[1]);

                    if (id == 0) {
                        clone.insertBefore(base);
                    } else {
                        clone.insertAfter($('.battle-unit[data-unit-id=' + data.units.attacker[(id - 1)][0] + ']'))
                    }
                }

                containers = container.find('.progress-container');
                containers.eq(0).attr('title', unit[2]).children('.progress-bar').width(unit[2] + '%');
                containers.eq(1).attr('title', unit[3]).children('.progress-bar').width(unit[3] + '%');
            });

            $.each(data.units.defender, function(id, unit) {
                var container = $('.battle-unit[data-unit-id=' + unit[0] + ']'),
                    containers = null,
                    base = null,
                    clone = null;

                activeUnits[unit[0]] = true;

                if (! container.length) {
                    base = defenderContainer.find('.battle-unit').eq(0);
                    clone = base.clone();

                    clone.attr('data-unit-id', unit[0]);

                    if (id == 0) {
                        clone.insertBefore(base);
                    } else {
                        clone.insertAfter($('.battle-unit[data-unit-id=' + data.units.defender[(id - 1)][0] + ']'))
                    }
                }

                containers = container.find('.progress-container');
                containers.eq(0).attr('title', unit[2]).children('.progress-bar').width(unit[2] + '%');
                containers.eq(1).attr('title', unit[3]).children('.progress-bar').width(unit[3] + '%');
            });

            $('.battle-unit').each(function() {
                if (! activeUnits[$(this).data('unit-id')]) {
                    $(this).remove();
                }
            });

            statusScroll.initiate(0, 0, 0, 0, true);
        });
    }

    function unitEvents() {
        $('#status-list').on('click', '#unit_create', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&unitId=' + $('#available_units').val();

            $.post(base + 'unit/train/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        }).on('click', '.unit-development-down', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&developmentId=' + $(this).attr('rel') + '&type=down';

            $.post(base + 'unit/priority/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        }).on('click', '.unit-development-up', function(event) {
            var params = 'token=' + $('#token').attr('value') + '&developmentId=' + $(this).attr('rel') + '&type=up';

            $.post(base + 'unit/priority/' + currentId + '.json', params, function(data) {
                token.attr('value', data.token);
                getAreaData(currentId);
            });
        });
    }

    structureEvents();
    unitEvents();
    armyEvents();

    gameInterval = window.setInterval(function() {
        updateGame();

        if (currentMenu == 'army' && currentAction == 'view') {
            updateArmy(currentId);
        } else if (currentMenu == 'combat' && currentAction == 'view') {
            updateBattle(currentId);
        }
    }, 1000);

    /*updateInterval = window.setInterval(function() {
        if (! active) {
            return;
        }

        now += 180;

        if (currentMenu == 'area' && currentAction == 'view') {
            $('.progress-container.active').each(function() {
                var container = $(this),
                    bar = container.children('div.progress-bar'),
                    timeLeft = bar.data('finish') - now,
                    progressed = Math.floor((bar.data('length') - timeLeft) / bar.data('length') * 100),
                    title = '';

                title = (progressed < 0) ? 'Preparing' : ((progressed > 100) ? 'Complete' : progressed + '%');
                progressed = (progressed < 0) ? 0 : ((progressed > 100) ? 100 : progressed);

                container.attr('title', title);
                bar.css('width', progressed + '%');
            });
        }

        if (currentMenu == 'army' && (currentAction == 'view' || currentAction == 'summary' || currentAction == 'selection')) {
            $('.progress-container.active').each(function() {
                var container = $(this),
                    bar = container.children('div.progress-bar'),
                    timeLeft = bar.data('finish') - now,
                    progressed = Math.floor((bar.data('length') - timeLeft) / bar.data('length') * 100),
                    title = '';

                title = (progressed < 0) ? 'Preparing' : ((progressed > 100) ? 'Arrived' : progressed + '%');
                progressed = (progressed < 0) ? 0 : ((progressed > 100) ? 100 : progressed);

                container.attr('title', title);
                bar.css('width', progressed + '%');
            });
        }
    }, 250);*/

    mapContainer.waitForImages(function() {
    }, function(loaded, count, success) {
        loadingProgressBar.css('width', Math.round(loaded / count * 100) + '%');

        if (loaded == count) {
            $('#loading-container').hide();
            mapContainer.show();
        }
    }, true);

});