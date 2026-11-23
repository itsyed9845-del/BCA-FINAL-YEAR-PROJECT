function sendDailyAgenda() {
  var email = Session.getActiveUser().getEmail();
  var today = new Date();
  
  // Get all events for today from the primary calendar
  var calendar = CalendarApp.getDefaultCalendar();
  var events = calendar.getEventsForDay(today);
  
  if (events.length === 0) {
    Logger.log("No events found for today.");
    // Optionally, you can still send an email saying your schedule is clear:
    MailApp.sendEmail(
      email, 
      "Your Daily Agenda: " + Utilities.formatDate(today, Session.getScriptTimeZone(), "MM/dd/yyyy"), 
      "Good morning!\n\nYou have no events scheduled for today. Enjoy your free time!"
    );
    return;
  }
  
  // Build the email body
  var htmlBody = "<h2>Your Daily Agenda for " + Utilities.formatDate(today, Session.getScriptTimeZone(), "MMMM dd, yyyy") + "</h2>";
  htmlBody += "<ul>";
  
  for (var i = 0; i < events.length; i++) {
    var event = events[i];
    var title = event.getTitle();
    var startTime = Utilities.formatDate(event.getStartTime(), Session.getScriptTimeZone(), "hh:mm a");
    var endTime = Utilities.formatDate(event.getEndTime(), Session.getScriptTimeZone(), "hh:mm a");
    var location = event.getLocation() ? "📍 " + event.getLocation() : "";
    var description = event.getDescription() ? "<br><i>" + event.getDescription() + "</i>" : "";
    
    htmlBody += "<li><b>" + startTime + " - " + endTime + "</b>: " + title;
    if (location) {
      htmlBody += "<br>" + location;
    }
    if (description) {
      htmlBody += description;
    }
    htmlBody += "</li><br>";
  }
  
  htmlBody += "</ul>";
  
  // Send the email
  MailApp.sendEmail({
    to: email,
    subject: "Your Daily Agenda - " + Utilities.formatDate(today, Session.getScriptTimeZone(), "MM/dd/yyyy"),
    htmlBody: htmlBody
  });
}