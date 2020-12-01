#!/bin/bash

set -e

export PIMCORE_PROJECT_ROOT="${PWD}"

CMD="vendor/bin/codecept run -c . -vvv"

# add suite if configured
if [[ -n "$PIMCORE_TEST_SUITE" ]]; then
    CMD="$CMD $PIMCORE_TEST_SUITE"
fi

# add test group if configured
if [[ -n "$PIMCORE_TEST_GROUP" ]]; then
    CMD="$CMD -g $PIMCORE_TEST_GROUP"
fi

# add env if configured
if [[ -n "$PIMCORE_TEST_ENV" ]]; then
    CMD="$CMD --env $PIMCORE_TEST_ENV"
fi

# generate json result file
CMD="$CMD --json"

echo $CMD
eval $CMD
