<?php

/**
 * 
 * Please read full (and updated) documentation at: 
 * https://github.com/omegaup/omegaup/wiki/Arena 
 *
 *
 * 
 * POST /contests/:id:/problem/new
 * Si el usuario tiene permisos de juez o admin, crea un nuevo problema para el concurso :id
 *
 * */

require_once("ApiHandler.php");

class NewContest extends ApiHandler
{
    
    private $private_users_list;
    
    protected function RegisterValidatorsToRequest()
    {  
        ValidatorFactory::stringNotEmptyValidator()->validate(
                RequestContext::get("title"),
                "title");
        
        ValidatorFactory::stringNotEmptyValidator()->validate(
                RequestContext::get("description"),
                "description");
        
        ValidatorFactory::dateRangeValidator(
                RequestContext::get("start_time"), 
                RequestContext::get("finish_time"))
                ->validate(RequestContext::get("start_time"), "start_time");
        
        // Calculate contest length:
        $contest_length = strtotime(RequestContext::get("finish_time")) - strtotime(RequestContext::get("start_time"));
        
        // Window_length NULL is accepted
        if(!is_null(RequestContext::get("window_length")))
        {
            ValidatorFactory::numericRangeValidator(
                    0, 
                    floor($contest_length)/60)
                    ->validate(RequestContext::get("window_length"), "window_length");
        }

        ValidatorFactory::numericValidator()->validate(
                RequestContext::get("public"),
                "public");
        
        ValidatorFactory::stringNotEmptyValidator()->validate(
                RequestContext::get("token"),
                "token");
        
        ValidatorFactory::numericRangeValidator(0, 100)->validate(
                RequestContext::get("scoreboard"), 
                "scoreboard");
        
        ValidatorFactory::numericRangeValidator(0, 1)->validate(
                RequestContext::get("points_decay_factor"), "points_decay_factor");
        
        ValidatorFactory::numericValidator()->validate(
                RequestContext::get("partial_score"), "partial_score");
        
        ValidatorFactory::numericRangeValidator(0, $contest_length)
                ->validate(RequestContext::get("submissions_gap"), "submissions_gap");
        
        ValidatorFactory::enumValidator(array("no", "yes", "partial"))
                ->validate(RequestContext::get("feedback"), "feedback");
        
        ValidatorFactory::numericRangeValidator(0, INF)
                ->validate(RequestContext::get("penalty"), "penalty");
        
        ValidatorFactory::enumValidator(array("contest", "problem", "none"))
                ->validate(RequestContext::get("penalty_time_start"), "penalty_time_start");
        
        ValidatorFactory::enumValidator(array("sum", "max"))
                ->validate(RequestContext::get("penalty_calc_policy"), "penalty_calc_policy");
                
        // Validate private_users request, only if the contest is private        
        if(RequestContext::get("public") == 0)
        {
            if(is_null(RequestContext::get("private_users")))
            {
               throw new ApiException( ApiHttpErrors::invalidParameter("If the Contest is not Public, private_users is required") );    
            }
            else
            {
                // Validate that the request is well-formed
                $this->private_users_list = json_decode(RequestContext::get("private_users"));
                if (is_null($this->private_users_list))
                {
                   throw new ApiException( ApiHttpErrors::invalidParameter("private_users is malformed") );    
                }                
                
                // Validate that all users exists in the DB
                foreach($this->private_users_list as $userkey)
                {
                    if (is_null(UsersDAO::getByPK($userkey)))
                    {
                       throw new ApiException( ApiHttpErrors::invalidParameter("private_users contains a user that doesn't exists") );    
                    }
                }                               
            }
        }
    
    }       
    
    protected function GenerateResponse() 
    {
        // Create and populate a new Contests object
        $contest = new Contests();              
        $contest->setTitle(RequestContext::get("title"));
        $contest->setDescription(RequestContext::get("description"));
        $contest->setStartTime(RequestContext::get("start_time"));
        $contest->setFinishTime(RequestContext::get("finish_time"));
        $contest->setWindowLength(RequestContext::get("window_length"));
        $contest->setDirectorId($this->_user_id);
        $contest->setRerunId(0);
        $contest->setPublic(RequestContext::get("public"));
        $contest->setToken(RequestContext::get("token"));
        $contest->setScoreboard(RequestContext::get("scoreboard"));
        $contest->setPointsDecayFactor(RequestContext::get("points_decay_factor"));
        $contest->setPartialScore(RequestContext::get("partial_score"));
        $contest->setSubmissionsGap(RequestContext::get("submissions_gap"));
        $contest->setFeedback(RequestContext::get("feedback"));
        $contest->setPenalty(RequestContext::get("penalty"));
        $contest->setPenaltyTimeStart(RequestContext::get("penalty_time_start"));
        $contest->setPenaltyCalcPolicy(RequestContext::get("penalty_calc_policy"));
        
                
        // Push changes
        try
        {
            // Begin a new transaction
            ContestsDAO::transBegin();
            
            // Save the contest object with data sent by user to the database
            ContestsDAO::save($contest);
                        
            // If the contest is private, add the list of allowed users
            if (RequestContext::get("public") == 0)
            {
                
                foreach($this->private_users_list as $userkey)
                {
                    // Create a temp DAO for the relationship
                    $temp_user_contest = new ContestsUsers( array(
                        "contest_id" => $contest->getContestId(),
                        "user_id" => $userkey,
                        "access_time" => "0000-00-00 00:00:00",
                        "score" => 0,
                        "time" => 0
                    ));                    
                    
                    // Save the relationship in the DB
                    ContestsUsersDAO::save($temp_user_contest);
                }
            }
            
            // End transaction transaction
            ContestsDAO::transEnd();

        }catch(Exception $e)
        {   
            // Operation failed in the data layer
            ContestsDAO::transRollback();
            throw new ApiException( ApiHttpErrors::invalidDatabaseOperation() );    
        }
        
    }
    
}

?>
