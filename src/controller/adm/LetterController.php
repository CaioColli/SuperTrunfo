<?php

namespace controller\adm;

use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrRequest;

use model\adm\DeckModel;
use model\adm\CardModel;

use model\user\UserModel;
use response\Messages;
use response\Responses;
use validation\AdmValidation;

class LetterController
{
    // 56 Linhas
    // 44 Futuras linhas
    // Inacabado, falta validação de quantidade máxima de cartas por deck
    public function NewCard(PsrRequest $request, PsrResponse $response)
    {
        $deckID = $request->getAttribute('deck_ID');

        $data = json_decode($request->getBody()->getContents(), true);

        $rules = AdmValidation::CardCreate();

        $errors = [];

        if (!$rules['card_Name']->validate($data['card_Name'])) {
            $errors[] = 'Nome inválido ou ausente.';
        }

        if (!$rules['card_Image']->validate($data['card_Image'])) {
            $errors[] = 'Url inválida ou ausente.';
        }

        if (!$rules['attributes']->validate($data['attributes'])) {
            $errors[] = 'Para criar a carta deve ser enviado exatos 5 atributos';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        $deckAttributes = DeckModel::GetDeckAttributes($deckID);
        $deckAttributesIDs = array_column($deckAttributes, 'attribute_ID');

        foreach ($data['attributes'] as $attribute) {
            if (!in_array($attribute['attribute_ID'], $deckAttributesIDs)) {
                $response->getBody()->write(json_encode([
                    'status' => 400,
                    'message' => 'Um dos atributos enviados não pertencem ao deck.',
                    'errors' => '',
                ]));
                return $response->withStatus(400);
            }
        }

        // Fazer validação de quantidade máxima de cartas

        // $letterQuantities = $letterModel->GetLetters($deckID);

        // if (is_array($letterQuantities) &&  count($letterQuantities) >= 30) {
        //     return Messages::Error400($response, ['A quantidade máxima de 30 cartas no deck foi atingida, para inserir uma nova carta, remova uma.']);
        // }

        CardModel::NewCard(
            $data['card_Name'],
            $data['card_Image'],
            $deckID,
            $data['attributes']
        );

        $response->getBody()->write(json_encode([
            'status' => 201,
            'message' => 'Carta criada com sucesso.',
            'errors' => '',
        ]));

        return $response->withStatus(201);
    }

    public function EditCard(PsrRequest $request, PsrResponse $response)
    {
        $deckID = $request->getAttribute('deck_ID');
        $cardID = $request->getAttribute('card_ID');

        $data = json_decode($request->getBody()->getContents(), true);

        $rules = AdmValidation::CardEdit();

        $errors = [];

        if (isset($data['card_Name']) && !$rules['card_Name']->validate($data['card_Name'])) {
            $errors[] = 'Nome inválido ou ausente.';
        }

        if (isset($data['card_Image']) && !$rules['card_Image']->validate($data['card_Image'])) {
            $errors[] = 'Url inválida ou ausente.';
        }

        if (isset($data['attributes']) && !$rules['attributes']->validate($data['attributes'])) {
            $errors[] = 'Para editar o atributo carta deve ser enviado exatos 5 atributos';
        }

        if (isset($data['attributes']) && !is_array($data['attributes'])) {
            $errors[] = 'Atributo passado inválido';
        }

        if (count($errors) > 0) {
            return Messages::Error400($response, $errors);
        }

        // Atualiza nome e imagem 
        if (isset($data['card_Name']) || isset($data['card_Image'])) {
            CardModel::EditCard(
                $deckID,
                $cardID,
                $data['card_Name'],
                $data['card_Image']
            );
        }

        // Atualiza atributos
        $attributes = $data['attributes'];

        if ($attributes) {
            foreach ($attributes as $attribute) {
                if (isset($attribute['attribute_ID']) || isset($attribute['attribute_Value'])) {
                    CardModel::EditCardAttributes(
                        $deckID,
                        $cardID,
                        $attribute['attribute_ID'],
                        $attribute['attribute_Value']
                    );
                }
            }
        }

        $response->getBody()->write(json_encode([
            'status' => 200,
            'message' => 'Carta editada com sucesso.',
            'errors' => '',
        ]));

        return $response->withStatus(200);
    }

    public function DeleteCard(PsrRequest $request, PsrResponse $response)
    {
        $deckID = $request->getAttribute('deck_ID');
        $cardID = $request->getAttribute('card_ID');

        if (CardModel::DeleteCard($deckID, $cardID)) {
            $response->getBody()->write(json_encode(
                [
                    'sucess' => "Carta deletada com sucesso.",
                    'status' => 200
                ]
            ));
            return $response->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(Responses::ERR_BAD_REQUEST));
            return $response->withStatus(400);
        }
    }

    public function GetCards(PsrRequest $request, PsrResponse $response)
    {
        $deckID = $request->getAttribute('deck_ID');

        $letters = CardModel::GetCards($deckID);

        if (!$letters) {
            $response->getBody()->write(json_encode([Responses::ERR_NOT_FOUND]));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode($letters));
        return $response->withStatus(200);
    }

    public function GetCard(PsrRequest $request, PsrResponse $response)
    {
        $deckID = $request->getAttribute('deck_ID');
        $cardID = $request->getAttribute('card_ID');

        $cardData = CardModel::GetCard($deckID, $cardID);

        if (!$cardData) {
            $response->getBody()->write(json_encode(Responses::ERR_NOT_FOUND));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode($cardData));
        return $response->withStatus(200);
    }
}
