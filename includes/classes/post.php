<?php
class Post
{
    private $user_obj;
    private $con;
    public function __construct($con, $user)
    {
        $this->con      = $con;
        $this->user_obj = new User($con, $user);
    }

    public function submitPost($body, $user_to)
    {
        $body        = strip_tags($body);
        $body        = mysqli_real_escape_string($this->con, $body);
        $check_empty = preg_replace('/\s+/', '', $body);

        if ($check_empty != "") {
            //Current Date and Time
            $date_added = date("Y-m-d H:i:s");
            //Get $username

            $added_by = $this->user_obj->getUsername();

            //If user is not on own profile, user_to is 'none'
            if ($user_to == $added_by) {
                $user_to = "none";
            }

            $query       = mysqli_query($this->con, "INSERT into posts values('', '$body', '$added_by', '$user_to', '$date_added', 'no', 'no', '0')");
            $returned_id = mysqli_insert_id($this->con);

            //Insert notification

            //Update post count for users
            $num_posts = $this->user_obj->getNumPosts();
            $num_posts++;
            $update_query = mysqli_query($this->con, "UPDATE users set num_posts='$num_posts' where username='$added_by'");

        }

    }



    public function loadPostsFriends($data, $limit)
    {
        $page         = $data['page'];
        $userLoggedIn = $this->user_obj->getUsername();
        if ($page == 1)
            $start = 0;
        else {
            $start = ($page - 1) * $limit;
        }
        $str        = "";
        $data_query = mysqli_query($this->con, "SELECT * from posts where deleted='no' order by id desc");

        if (mysqli_num_rows($data_query)) {

            $num_iterations = 0;
            $count          = 1;

            while ($row = mysqli_fetch_array($data_query)) {
                $id        = $row['id'];
                $body      = $row['body'];
                $added_by  = $row['added_by'];
                $date_time = $row['date_added'];
                // prepare user_to string so it can be included even if not posted to a user
                if ($row['user_to'] == "none") {
                    $user_to = "";
                } else {
                    $user_to_obj  = new User($con, $row['user_to']);
                    $user_to_name = $user_to_obj->getFirstAndLastName();
                    $user_to      = "to <a href='" . $row['user_to'] . "'>" . $user_to_name . "</a>";

                }

                // check if the use account is closed or not
                $added_by_obj = new User($this->con, $added_by);
                if ($added_by_obj->isClosed()) {
                    continue;
                }

                $user_logged_obj = new User($this->con, $userLoggedIn);
                if ($user_logged_obj->isFriend($added_by)) {


                    if ($num_iterations++ < $start) {
                        continue;
                    }

                    //Once 10 posts have been loaded, break

                    if ($count > $limit) {
                        break;
                    } else {

                        $count++;
                    }

                    $user_details_query = mysqli_query($this->con, "SELECT first_name, last_name, profile_pic from users where username='$added_by'");
                    $user_row           = mysqli_fetch_array($user_details_query);
                    $first_name         = $user_row['first_name'];
                    $last_name          = $user_row['last_name'];
                    $profile_pic        = $user_row['profile_pic'];


                    ?>
                    <script type="text/javascript">
                       function toggle<?php echo $id; ?>() {
                         var element = document.getElementById("toggleComment<?php echo $id; ?>");

                         if( element.style.display == "block")
                           element.style.display = "none";
                         else{
                           element.style.display = "block";
                         }

                       }

                    </script>
                    <?php

                    //timestamp

                    $date_time_now = date("Y-m-d H:i:s");
                    $start_date    = new DateTime($date_time);
                    $end_date      = new DateTime($date_time_now);

                    $interval = $start_date->diff($end_date);

                    if ($interval->y >= 1) {
                        if ($interval == 1) {
                            $time_message = $interval->y . " year ago";
                        } else {
                            $time_message = $interval->y . " years ago";
                        }
                    } else if ($interval->m >= 1) {
                        if ($interval->d == 0)
                            $days = "ago";
                        elseif ($interval->d == 1) {
                            $days = $interval->d . " day ago";
                        } else {
                            $days = $interval->d . " days ago";
                        }


                        if ($interval->m == 1) {
                            $time_message = $interval->m . " month" . $days;
                        } else {
                            $time_message = $interval->m . " months" . $days;
                        }

                    } elseif ($interval->d >= 1) {
                        if ($interval->d == 1) {
                            $time_message = "yesterday";
                        } else {
                            $time_message = $interval->d . " days ago";
                        }
                    } elseif ($interval->h >= 1) {
                        if ($interval->h >= 1) {
                            $time_message = $interval->h . " hours ago";
                        } else {
                            $time_message = $interval->h . " hour ago";

                        }

                    } elseif ($interval->i >= 1) {
                        if ($interval->i == 1) {
                            $time_message = $interval->i . " minute ago";
                        } else {
                            $time_message = $interval->i . " minutes ago";

                        }

                    } else {
                        if ($interval->s < 30) {
                            $time_message = "Just now";
                        } else {
                            $time_message = $interval->s . " seconds ago";

                        }

                    }

                    $str .= "<div class='status_post' onClick='javascript:toggle$id()'>
              <div class='post_profile_pic'>
              <img src='$profile_pic' width='50'>
              </div>
            <div class='total'>
              <div class='posted_by' style='color:#ACACAC;'>
              <a href='added_by'>$first_name $last_name </a> $user_to &nbsp;<br>

              </div>
              <br>

              <div id='post_body'>
              $body
              <br>
              </div>
              </div>
              <div style='float:right;color:#bdc3c7'>$time_message
              </div>
              </div>
              <div class='post_comment' id='toggleComment$id' style='display:none;'>
              <iframe src='comment_frame.php?post_id=$id' id='comment_iframe' frameborder='0'></iframe>
              </div>
              <hr>";

                }
            } //EndWhileloop
            if ($count > $limit) {
                $str .= "<input type='hidden' class='nextPage' value='" . ($page + 1) . "'><input type='hidden' class='noMorePosts' value='false'>";
            } else {
                $str .= "<input type='hidden' class='noMorePosts' value='true'><p style='text-align:center;'>  </p>";

            }

        }

        echo $str;
    }
}

?>
