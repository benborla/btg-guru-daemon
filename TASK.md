[x] - Implement GoalServe API
[x] - Refactor how API is being coded in the app, it should have an API Interface that would unify all API calls
[x] - Add API configuration 
[x] - Access schedules api and save it in the database
[x] - if there is not match today, return the data schedules (as the scoreboard)
[x] - setup a job that will the standings every 12 hour (https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/afl/standings) 

[x] - Setup a pipeline that automatically triggers a deployment on main branch
[x] - (get_match_data) create a helper function that identies the teams that's going to play today
    - [x] - has_match_today?
    - [x] - get_current_round
    - [x] - get_match_id
[x] - based on the `get_match_data` we should then run a command that retrieves the matchdata similar to AFL Sync Command

