#!/bin/bash

echo "🚀 Testing OpenAI Communication Flow"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check Redis
check_redis() {
    echo -e "${YELLOW}🔍 Checking Redis connection...${NC}"
    if docker exec starter_kit_backend_redis redis-cli ping | grep -q "PONG"; then
        echo -e "${GREEN}✅ Redis is running${NC}"
        return 0
    else
        echo -e "${RED}❌ Redis is not responding${NC}"
        return 1
    fi
}

# Function to check request queue status
check_queue() {
    echo -e "${YELLOW}📊 Checking Redis request queue status...${NC}"
    local queue_length=$(docker exec starter_kit_backend_redis redis-cli llen openai_requests)
    echo -e "${GREEN}📨 Messages in queue: ${queue_length}${NC}"
}

# Function to send test message. Replace the chat_id/user_id below with real
# UUIDs from your local seed data (a chat with an active assistant
# participant, and a real user id) — this job now requires string UUIDs,
# not the placeholder integer ids this script used pre-multitenancy.
send_test_message() {
    echo -e "${YELLOW}📤 Sending test message...${NC}"
    docker exec starter_kit_backend_app php artisan test:openai-job "<chat-uuid>" "<user-uuid>" "Teste da comunicação OpenAI via Redis"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Test message sent successfully${NC}"
    else
        echo -e "${RED}❌ Failed to send test message${NC}"
        return 1
    fi
}

# Function to check for responses on the openai_responses list — this is
# what the Python worker (starter_kit_ai_microservice) LPUSHes onto, and
# what `php artisan listen:openai-responses` RPOPs from.
check_responses() {
    echo -e "${YELLOW}🔍 Checking for OpenAI responses...${NC}"
    local response_length=$(docker exec starter_kit_backend_redis redis-cli llen openai_responses)

    if [ "$response_length" -gt 0 ]; then
        echo -e "${GREEN}📨 Found ${response_length} response(s):${NC}"
        docker exec starter_kit_backend_redis redis-cli lrange openai_responses 0 -1
    else
        echo -e "${YELLOW}⚠️ No responses found yet${NC}"
    fi
}

# Function to start listener
start_listener() {
    echo -e "${YELLOW}🎧 Starting OpenAI response listener...${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the listener${NC}"

    # Start the listener in background — in a normal docker compose run
    # this already runs continuously as its own `openai-listener` service
    # (see docker-compose.yml), this is only for running it standalone.
    docker exec starter_kit_backend_app php artisan listen:openai-responses &
    local listener_pid=$!

    echo -e "${GREEN}✅ Listener started with PID: ${listener_pid}${NC}"
    echo -e "${YELLOW}To stop the listener, run: kill ${listener_pid}${NC}"
}

# Main execution
main() {
    echo "Starting tests..."

    # Check Redis
    if ! check_redis; then
        echo -e "${RED}❌ Cannot proceed without Redis${NC}"
        exit 1
    fi

    # Check initial queue status
    check_queue

    # Send test message
    if ! send_test_message; then
        echo -e "${RED}❌ Test failed${NC}"
        exit 1
    fi

    # Wait a bit
    echo -e "${YELLOW}⏳ Waiting 2 seconds...${NC}"
    sleep 2

    # Check queue status again
    check_queue

    # Check for responses
    check_responses

    echo ""
    echo -e "${GREEN}🎉 Test completed!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Make sure starter_kit_ai_microservice is running (docker compose up -d in that repo)"
    echo "2. The openai-listener service in this repo's docker-compose.yml already runs"
    echo "   'php artisan listen:openai-responses' continuously — no need to start it manually"
    echo "   unless you're running it standalone (see start_listener() above)."
    echo "3. Send another test message to see the complete flow"
}

# Run main function
main "$@"
