<?php
{
    "standardBusinessDocumentHeader" : {
      "headerVersion" : "1.0",
      "sender" : [ {
        "identifier" : {
          "value" : "{{ $senderId }}",
          "authority" : "iso6523-actorid-upis"
        }
      } ],
      "receiver" : [ {
        "identifier" : {
          "value" : "{{ $receiverId }}",
          "authority" : "iso6523-actorid-upis"
        }
      } ],
      "documentIdentification" : {
        "standard" : "urn:no:difi:arkivmelding:xsd::arkivmelding",
        "typeVersion" : "1.0",
        "instanceIdentifier" : "{{ $messageId }}",
        "type" : "arkivmelding"
      },
      "businessScope" : {
        "scope" : [ {
          "type" : "ConversationId",
          "instanceIdentifier" : "{{ $conversationId }}",
          "identifier" : "urn:no:difi:profile:arkivmelding:administrasjon:ver1.0",
          "scopeInformation" : [ {
            "expectedResponseDateTime" : "{{ $responseDt }}"
          } ]
        } ]
      }
    },
    "arkivmelding" : {
      "hoveddokument" : "{{ $mainDocument }}"
    }
}
