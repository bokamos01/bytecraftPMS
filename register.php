<?php
if(session_status()==PHP_SESSION_NONE){session_start();}
$registration_message='';
$formData=['firstName'=>'','lastName'=>'','gender'=>'','email'=>'','password'=>'','confirmPassword'=>'','company'=>'','dateRegistered'=>date('Y-m-d')];
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $formData['firstName']=isset($_POST['firstName'])?trim($_POST['firstName']):'';
    $formData['lastName']=isset($_POST['lastName'])?trim($_POST['lastName']):'';
    $formData['gender']=isset($_POST['gender'])?trim($_POST['gender']):'';
    $formData['email']=isset($_POST['email'])?trim($_POST['email']):'';
    $formData['password']=isset($_POST['password'])?$_POST['password']:'';
    $formData['confirmPassword']=isset($_POST['confirmPassword'])?$_POST['confirmPassword']:'';
    $formData['company']=isset($_POST['company'])?trim($_POST['company']):'';
    $formData['dateRegistered']=isset($_POST['dateRegistered'])?$_POST['dateRegistered']:date('Y-m-d');
    $errors=[];
    if(empty($formData['firstName']))$errors[]='First name is required';
    if(empty($formData['lastName']))$errors[]='Last name is required';
    if(empty($formData['gender']))$errors[]='Gender is required';
    if(empty($formData['email']))$errors[]='Email is required';
    if(empty($formData['company']))$errors[]='Company is required';
    if(empty($formData['dateRegistered']))$errors[]='Date registered is required';
    if(!empty($formData['dateRegistered'])){
        $registrationDate=strtotime($formData['dateRegistered']);
        $today=strtotime(date('Y-m-d'));
        if($registrationDate>$today)$errors[]='Registration date cannot be in the future';
    }
    if(empty($formData['password']))$errors[]='Password is required';
    elseif(strlen($formData['password'])<6)$errors[]='Password must be at least 6 characters long';
    if($formData['password']!==$formData['confirmPassword'])$errors[]='Passwords do not match';
    if(empty($errors)){
        $hashedPassword=hash('sha256',$formData['password']);
        $registration_message='<div class="success-container"><p>Registration successful!</p><p>First Name: '.htmlspecialchars($formData['firstName']).'</p><p>Last Name: '.htmlspecialchars($formData['lastName']).'</p><p>Gender: '.htmlspecialchars($formData['gender']).'</p><p>Email: '.htmlspecialchars($formData['email']).'</p><p>Company: '.htmlspecialchars($formData['company']).'</p><p>Date Registered: '.htmlspecialchars($formData['dateRegistered']).'</p><p><a href="login.php">Proceed to login</a></p></div>';
        $formData=['firstName'=>'','lastName'=>'','gender'=>'','email'=>'','password'=>'','confirmPassword'=>'','company'=>'','dateRegistered'=>date('Y-m-d')];
    }else{
        $registration_message='<div class="error-container"><p class="error-message">Please correct the following errors: '.implode(', ',$errors).'</p></div>';
    }
}
$content='
<style>
    .login-container {
        max-width: 400px;
        margin: 20px auto;
        background-color: #fff;
        border-radius: 3px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: 600px;
        overflow-y: auto;
    }
    .login-container h2 {
        text-align: center;
    }
    .form-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        background-color: #ffffe0;
        box-sizing: border-box;
    }
    .form-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 10px;
    }
</style>
<div class="outer-container">
    <div class="login-container">
        <h2>CREATE ACCOUNT</h2>
        '.$registration_message.'
        <form action="'.htmlspecialchars($_SERVER["PHP_SELF"]).'" method="POST" class="customer-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name <span class="required">*</span></label>
                    <input type="text" id="firstName" name="firstName" class="form-input" value="'.htmlspecialchars($formData['firstName']).'" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name <span class="required">*</span></label>
                    <input type="text" id="lastName" name="lastName" class="form-input" value="'.htmlspecialchars($formData['lastName']).'" required>
                </div>
            </div>
            <div class="form-group">
                <label for="gender">Gender <span class="required">*</span></label>
                <select id="gender" name="gender" class="form-input" required>
                    <option value="">Select Gender</option>
                    <option value="M"'.($formData['gender']==='M'?' selected':'').'>Male</option>
                    <option value="F"'.($formData['gender']==='F'?' selected':'').'>Female</option>
                    <option value="O"'.($formData['gender']==='O'?' selected':'').'>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-input" value="'.htmlspecialchars($formData['email']).'" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" value="" required>
                    <span class="field-info">Minimum 6 characters</span>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" value="" required>
                </div>
            </div>
            <div class="form-group">
                <label for="company">Company <span class="required">*</span></label>
                <input type="text" id="company" name="company" class="form-input" value="'.htmlspecialchars($formData['company']).'" required>
            </div>
            <input type="hidden" id="dateRegistered" name="dateRegistered" value="'.htmlspecialchars($formData['dateRegistered']).'">
            <div class="form-buttons">
                <button type="submit">REGISTER</button>
                <button type="reset" class="secondary-button">Reset</button>
            </div>
        </form>
    </div>
</div>';
include 'master.php';
?>
