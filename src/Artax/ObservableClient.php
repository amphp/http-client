<?php

namespace Artax;

interface ObservableClient extends Observable {

    const USER_AGENT = 'Artax/0.6.0-dev (PHP5.4+)';

    const REQUEST = 'request';
    const SOCKET = 'socket';
    const HEADERS = 'headers-rcvd';
    const BODY_DATA = 'body-data';
    const CANCEL = 'cancel';
    const RESPONSE = 'response';
    const REDIRECT = 'redirect';
    const ERROR = 'error';
    const SOCK_DATA_OUT = 'data-out';
    const SOCK_DATA_IN = 'data-in';
    
    function setOption($optionName, $value);
    function setAllOptions(array $options);
    function cancel(Request $request);
    function cancelAll();
}
