<?php

namespace Artax\Negotiation;

interface RangeTerm extends NegotiableTerm {
    function getTopLevelType();
    function getSubType();
    function rangeMatches($term);
}
