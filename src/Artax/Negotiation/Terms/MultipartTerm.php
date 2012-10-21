<?php

namespace Artax\Negotiation\Terms;

interface MultipartTerm extends Negotiable {
    function getTopLevelType();
    function getSubType();
    function rangeMatches($term);
}
