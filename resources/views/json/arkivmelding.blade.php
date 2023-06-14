<?php
{
    "standardBusinessDocumentHeader" : {
      "headerVersion" : "1.0",
      "sender" : [ {
        "identifier" : {
          "value" : "{{ $message->sender_id }}",
          "authority" : "iso6523-actorid-upis"
        }
      } ],
      "receiver" : [ {
        "identifier" : {
          "value" : "{{ $message->receiver_id }}",
          "authority" : "iso6523-actorid-upis"
        }
      } ],
      "documentIdentification" : {
        "standard" : "{{ $message->documentStandard }}",
        "typeVersion" : "1.0",
        "instanceIdentifier" : "{{ $message->id }}",
        "type" : "arkivmelding"
      },
      "businessScope" : {
        "scope" : [ {
          "type" : "ConversationId",
          "instanceIdentifier" : "{{ $message->conversationId }}",
          "identifier" : "{{ $message->processIdentifier }}",
          "scopeInformation" : [ {
            "expectedResponseDateTime" : "{{ $message->getResponseDt() }}"
          } ]
        } ]
      }
    },
    "arkivmelding" : {
      "hoveddokument" : "{{ $message->mainDocument }}"
    }
}
