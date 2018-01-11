

import java.io.*;
import javax.servlet.ServletConfig;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
@WebServlet("/")
public class Manager extends HttpServlet {
  
  private static final long serialVersionUID = 1L;
  private String message;

  public void init() throws ServletException {
  }

  public void doGet(HttpServletRequest request, HttpServletResponse response)
     throws ServletException, IOException {
     response.sendError(401, "No autorizado!" );
  }
  
  public void doPost(HttpServletRequest request, HttpServletResponse response)
      throws ServletException, IOException {
    
     // Set response content type
        response.setContentType("text/html");
    
        // Actual logic goes here.
        PrintWriter out = response.getWriter();
        
       ////    
        ProcessBuilder pb = new ProcessBuilder("/opt/dspace/import.sh");
        Process p = pb.start();
        BufferedReader reader = new BufferedReader(new InputStreamReader(p.getInputStream()));
        String line = null;
        while ((line = reader.readLine()) != null)
        {
        out.println(line);
        }
        
        BufferedReader stdError = new BufferedReader(new 
            InputStreamReader(p.getErrorStream()));
        while ((line = stdError.readLine()) != null)
        {
        out.println(line);
        }
             
  }

  public void destroy() {
     // do nothing.
  }
}
