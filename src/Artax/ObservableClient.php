<?php

namespace Artax;

interface ObservableClient extends Observable {
    
    const SOCKET = 'socket';
    const CANCEL = 'cancel';
    const REQUEST = 'request';
    const HEADERS = 'headers';
    const BODY_DATA = 'bodyData';
    const REDIRECT = 'redirect';
    const RESPONSE = 'response';
}

