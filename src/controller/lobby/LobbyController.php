<?php

namespace controller\lobby;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use model\lobby\LobbyModel;
use model\lobby\MatchModel;
use model\adm\DeckModel;
use model\user\UserModel;

use response\Messages;

use Exception;
use validation\LobbyValidation;

class LobbyController
{
    public function CreateLobby(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');

        $data = json_decode($request->getBody()->getContents(), true);

        $lobbyName = $data['lobby_Name'];
        $deckID = $data['deck_ID'];

        $deck = DeckModel::GetDeck($deckID);
        $deckIsAvailable = $deck['deck_Is_Available'];

        $rules = LobbyValidation::LobbyCreate();

        $playerInLobby = LobbyModel::VerifyPlayerInLobby($user['user_ID']);

        $errors = [];

        if ($playerInLobby) {
            $errors[] = 'Você já está no lobby, saia do lobby atual para criar outro.';
        }

        if (!$rules['lobby_Name']->validate($data['lobby_Name'])) {
            $errors[] = 'Nome do lobby é obrigatório e deve conter no mínimo 3 e no maximo 50 caracteres.';
        }

        if (empty($deckID)) {
            $errors[] = 'É obrigatório selecionar um deck.';
        }

        if (!$deck) {
            $errors[] = 'Deck não encontrado.';
        }

        if ($deckIsAvailable === false) {
            $errors[] = 'Deck não disponivel.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        // Cria Lobby e insere o host no lobby
        $lobby = LobbyModel::CreateLobby(
            $lobbyName,
            $user['user_ID'],
            $deckID
        );

        // Exibe o lobby criado
        $lobbyData = LobbyModel::GetLobby($lobby);

        $response->getBody()->write(json_encode($lobbyData));
        return $response->withStatus(201);
    }

    public function GetLobbys(Request $request, Response $response)
    {
        $lobbys = LobbyModel::GetLobbys();

        if (!$lobbys || count($lobbys) == 0) {
            return Messages::Error404($response, ['Nenhum lobby encontrado ou criado.']);
        }

        $response->getBody()->write(json_encode([
            'lobbies' => $lobbys
        ]));

        return $response->withStatus(200);
    }

    public function GetLobby(Request $request, Response $response)
    {
        $lobbyID = $request->getAttribute('lobby_ID');

        $lobbyData = LobbyModel::GetLobby($lobbyID);

        if (!$lobbyData) {
            return Messages::Error404($response, ['Lobby não encontrado.']);
        }

        $response->getBody()->write(json_encode($lobbyData));
        return $response->withStatus(200);
    }

    public function JoinLobby(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $lobbyData = LobbyModel::GetExistingLobby($lobbyID);
        $lobbyPlayers = LobbyModel::GetTotalPlayersLobby($lobbyID);
        $playerInLobby = LobbyModel::VerifyPlayerInLobby($user['user_ID']);

        $errors = [];

        if ($playerInLobby) {
            if ($playerInLobby['lobby_ID'] == $lobbyID) {
                $errors[] = 'Vocé já esta no lobby atual.';
            } else {
                $errors[] = 'Vocé já está no lobby, saia do lobby atual para entrar em outro.';
            }
        }

        if (!$lobbyData) {
            $errors[] = 'Lobby não encontrado.';
        }

        if (count($lobbyPlayers) >= 30) {
            $errors[] = 'Lobby cheio.';
        }

        if ($lobbyData['lobby_Available'] === 0) {
            $errors[] = 'Lobby fechado.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        LobbyModel::JoinLoby($user['user_ID'], $lobbyID);

        $response->getBody()->write(json_encode([
            'players' => LobbyModel::GetLobbyPlayers($lobbyID)
        ]));
        return $response->withStatus(200);
    }

    public function RemovePlayer(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID  = $request->getAttribute('lobby_ID');

        $data = json_decode($request->getBody()->getContents(), true);

        $playerID = $data['user_ID'];

        $lobbyData  = LobbyModel::GetExistingLobby($lobbyID);
        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);

        $isHost = ($user['user_ID'] == $lobbyHost);
        $isPlayer = ($user['user_ID'] == $playerID);

        $errors = [];

        if (!$lobbyData) {
            $errors[] = 'Lobby não encontrado.';
        }

        // Verifica se é o host ou o próprio player
        if (!$isHost && !$isPlayer) {
            $errors[] = 'Você não tem permissão para remover este jogador.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        LobbyModel::RemovePlayer($playerID, $lobbyID);

        $response->getBody()->write(json_encode([
            'status' => 201,
            'message' => 'Jogador removido com sucesso.',
            'errors' => '',
        ]));
        return $response->withStatus(200);
    }

    public function EditLobby(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $data = json_decode($request->getBody()->getContents(), true);

        $lobbyData = LobbyModel::GetLobby($lobbyID);
        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);

        $lobbyName = $data['lobby_Name'] ?? $lobbyData['lobby_Name'];
        $lobbyAvailable = (int)$data['lobby_Available'] ?? $lobbyData['lobby_Available'];
        $deckID = $data['deck_ID'] ?? $lobbyData['deck_ID'];

        $deckData = DeckModel::GetDeck($deckID);

        $rules = LobbyValidation::EditLobby();

        $isHost = ($user['user_ID'] == $lobbyHost);

        $errors = [];

        if (!$lobbyID) {
            $errors[] = 'Lobby não encontrado ou ID incorreto.';
        }

        if ($isHost) {
            if (!$rules['lobby_Name']->validate($data['lobby_Name'])) {
                $errors[] = 'Nome inválido ou ausente.';
            }

            if (!$rules['lobby_Available']->validate($data['lobby_Available'])) {
                $errors[] = 'O valor deve ser booleano.';
            }

            if (isset($data['deck_ID'])) {
                if (($data['deck_ID']) && !$rules['deck_ID']->validate($data['deck_ID'])) {
                    $errors[] = 'Deck inválido ou ausente.';
                } elseif (!$deckData) {
                    $errors[] = 'Deck inexistente';
                } elseif ($deckData['deck_Is_Available'] === 0) {
                    $errors[] = 'Deck indisponível';
                }
            }

            if ($lobbyData['lobby_Status'] != 'Aguardando') {
                $errors[] = 'Confuração do lobby desativada durante o jogo.';
            }
        } else {
            $errors[] = 'Você precisa ser o host do lobby para editar o lobby.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        LobbyModel::EditLobby(
            $lobbyID,
            $lobbyName,
            $lobbyAvailable,
            $deckID
        );

        $response->getBody()->write(json_encode(LobbyModel::GetLobby($lobbyID)));
        return $response->withStatus(200);
    }

    public function DeleteLobby(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $lobbyData = LobbyModel::GetLobby($lobbyID);
        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);

        $isHost = ($user['user_ID'] == $lobbyHost);

        $errors = [];

        if (!$lobbyData) {
            $errors[] = 'Lobby não encontrado ou ID incorreto.';
        }

        if (!$isHost) {
            $errors[] = 'Você precisa ser o host do lobby para deletar o lobby.';
        }


        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        LobbyModel::DeleteLobby($lobbyID);

        $response->getBody()->write(json_encode([
            'status' => 201,
            'message' => 'Lobby deletado com sucesso.',
            'errors' => '',
        ]));
        return $response->withStatus(200);
    }

    public function StartLobby(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $lobbyData = LobbyModel::GetExistingLobby($lobbyID);
        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);

        $isHost = $user['user_ID'] == $lobbyHost;

        $errors = [];

        if ($isHost) {
            if (!$lobbyData) {
                $errors[] = 'Lobby não encontrado ou ID incorreto.';
            }

            if (count(LobbyModel::GetTotalPlayersLobby($lobbyID)) < 2) {
                $errors[] = 'Lobby precisa ter pelo menos 2 jogadores.';
            }

            if (LobbyModel::GetLobbyStatus($lobbyID) != 'Aguardando') {
                $errors[] = 'Partida já iniciada.';
            }
        } else {
            $errors[] = 'Você precisa ser o host do lobby para iniciar o lobby.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        LobbyModel::StartLobby($lobbyID);

        $response->getBody()->write(json_encode([
            'status' => 201,
            'message' => 'Partida iniciada com sucesso.',
            'errors' => '',
        ]));
        return $response->withStatus(200);
    }

    public function DistributeCards(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $lobbyData = LobbyModel::GetExistingLobby($lobbyID);
        $lobbyPlayers = LobbyModel::GetTotalPlayersLobby($lobbyID);
        $distributedCards = MatchModel::CheckDistributedCards($lobbyID);
        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);

        $isHost = $user['user_ID'] == $lobbyHost;

        $errors = [];

        if ($isHost) {
            if (!$lobbyData) {
                $errors[] = 'Lobby não encontrado ou ID incorreto.';
            }

            if ($lobbyData['lobby_Status'] === 'Aguardando' || $lobbyData['lobby_Available'] === 1) {
                $errors[] = 'Não foi possivel dividir as cartas para iniciar o jogo pois o lobby não foi iniciado.';
            }

            if (count($lobbyPlayers) < 2) {
                $errors[] = 'Lobby precisa ter pelo menos 2 jogadores.';
            }

            if (count($distributedCards) > 0) {
                $errors[] = 'Cartas já distribuidas.';
            }
        } else {
            $errors[] = 'Você precisa ser o host do lobby para deletar o lobby.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        MatchModel::DistributeCards($lobbyID, $user['user_ID']);

        $response->getBody()->write(json_encode([
            'status' => 200,
            'message' => 'Cartas distribuidas com sucesso.',
            'errors' => '',
        ]));
        return $response->withStatus(200);
    }

    public function FirstPlay(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $gameFlow = MatchModel::GetGameFlow($lobbyID);
        $currentTurn = $gameFlow['current_Turn'];
        $currentRound = $gameFlow['current_Round'];

        $data = json_decode($request->getBody()->getContents(), true);
        $attributeID = $data['attribute_ID'];

        $lobbyHost = LobbyModel::GetLobbyHost($lobbyID);
        $isHost = $user['user_ID'] == $lobbyHost;

        $distributedCards = MatchModel::CheckDistributedCards($lobbyID);

        $errors = [];

        if (count($distributedCards) === 0) {
            $errors[] = 'Cartas ainda não foram distribuidas.';
        }

        if ($currentRound === 1 && !$isHost) {
            $errors[] = 'Somente o host pode jogar na primeira rodada.';
        }

        if ($currentTurn != $user['user_ID']) {
            $errors[] = 'Não é sua vez de jogar.';
        }

        if (!$attributeID) {
            $errors[] = 'É necessário passar um atributo.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        //

        MatchModel::PlayFirstCard($lobbyID, $user['user_ID'], $attributeID);

        $nextPlayer = MatchModel::SetNextPlayer($lobbyID, $user['user_ID']);
        MatchModel::UpdateGameFlow($lobbyID, $nextPlayer);

        $response->getBody()->write(json_encode([
            'status' => 200,
            'message' => 'Carta jogada com sucesso.',
            'errors' => '',
        ]));
        return $response->withStatus(200);
    }

    public function PlayTurn(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $lobbyID = $request->getAttribute('lobby_ID');

        $choosedAttribute = MatchModel::GetChoosedAttribute($lobbyID);
        $gameFlow = MatchModel::GetGameFlow($lobbyID);

        $currentTurn = $gameFlow['current_Turn'];
        $currentRound = $gameFlow['current_Round'];

        $errors = [];

        if (!$choosedAttribute['attribute_ID']) {
            $errors[] = 'O atributo ainda não foi escolhido pelo primeiro jogador.';
        }

        if ($currentTurn != $user['user_ID']) {
            $errors[] = 'Não é sua vez de jogar.';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        MatchModel::PlayTurn($lobbyID, $user['user_ID']);

        $remainingPlayers = MatchModel::GetRemainingPlayers($lobbyID, $currentRound);

        if (empty($remainingPlayers)) {
            $winnerResult = LobbyController::GetWinner($lobbyID, $currentRound);
            $response->getBody()->write($winnerResult);

            $getWinner = MatchModel::GetRoundWinner($lobbyID);
            var_dump($getWinner);
            // Aqui o método que distribui a carta para o vencedor da rodada

            MatchModel::IncrementRound($lobbyID);

            // Colocar aqui como proximo o vencedor da rodada!!!! //
            $nextPlayer = MatchModel::SetNextPlayer($lobbyID, $getWinner);
            MatchModel::UpdateGameFlow($lobbyID, $nextPlayer);

            return $response->withStatus(200);
        } else {
            $nextPlayer = MatchModel::SetNextPlayer($lobbyID, $user['user_ID']);
            MatchModel::UpdateGameFlow($lobbyID, $nextPlayer);
        }
    }

    public function GetWinner($lobbyID, $currentRound)
    {
        $matchResult = MatchModel::DetermineWinner($lobbyID, $currentRound);

        // Não foi jogada todas as cartas para definir um vencedor
        // O vencedor não foi encontrado no lobby

        if ($matchResult['status'] === 'empate') {
            return (json_encode([
                'status' => 200,
                'message' => $matchResult['message'],
                'errors' => $matchResult['errors']
            ]));
        } else {
            return (json_encode([
                'status' => 200,
                'winner' => $matchResult['winner_user_name'],
                'card' => $matchResult['winner_card_name'],
                'round' => $matchResult['atual_round']
            ]));
        }
    }
}
