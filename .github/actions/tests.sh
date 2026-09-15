#!/bin/bash

set -e

npx cypress run  --headless --browser chrome  --config '{"specPattern":["plugins/generic/reviewerRecommendationManager/cypress/tests/functional/*.cy.js"]}'
