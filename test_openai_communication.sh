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
    if docker exec dashboard_addresses_redis redis-cli ping | grep -q "PONG"; then
        echo -e "${GREEN}✅ Redis is running${NC}"
        return 0
    else
        echo -e "${RED}❌ Redis is not responding${NC}"
        return 1
    fi
}

# Function to check queue status
check_queue() {
    echo -e "${YELLOW}📊 Checking Redis queue status...${NC}"
    local queue_length=$(docker exec dashboard_addresses_redis redis-cli llen openai_requests)
    echo -e "${GREEN}📨 Messages in queue: ${queue_length}${NC}"
}

# Function to send test message
send_test_message() {
    echo -e "${YELLOW}📤 Sending test message...${NC}"
    docker exec dashboard_addresses_app php artisan test:openai-job 1 1 "Teste da comunicação OpenAI via Redis"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Test message sent successfully${NC}"
    else
        echo -e "${RED}❌ Failed to send test message${NC}"
        return 1
    fi
}

# Function to check for responses
check_responses() {
    echo -e "${YELLOW}🔍 Checking for OpenAI responses...${NC}"
    local response_keys=$(docker exec dashboard_addresses_redis redis-cli keys "openai_response:*")
    
    if [ -n "$response_keys" ]; then
        echo -e "${GREEN}📨 Found responses:${NC}"
        echo "$response_keys"
    else
        echo -e "${YELLOW}⚠️ No responses found yet${NC}"
    fi
}

# Function to start listener
start_listener() {
    echo -e "${YELLOW}🎧 Starting OpenAI response listener...${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the listener${NC}"
    
    # Start the listener in background
    docker exec dashboard_addresses_app php artisan listen:openai-responses-pubsub &
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
    echo "1. Start your Python worker in another terminal"
    echo "2. Run: docker exec dashboard_addresses_app php artisan listen:openai-responses-pubsub"
    echo "3. Send another test message to see the complete flow"
}

# Run main function
main "$@"
