<!--Alert style-->
<?
   if (isset($_SESSION['message'])) {
           $message = $_SESSION['message'];
           $alert_class = $_SESSION['alert_class'];

	#Print The code that have been saved to print.
        echo "";
          echo "".$message."";
		 echo "".$message."";
        
    unset($_SESSION['message']);
    unset($_SESSION['alert_class']);
}
?>