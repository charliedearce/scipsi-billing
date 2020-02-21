Imports System.Data.SqlClient

Module SqlStatements
    Public Sub Systime()
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("UPDATE tbl_settings SET sysdate='{0}'", Date.Now), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            Main.Label2.Visible = False
        Catch ex As Exception
            Main.Label2.Visible = True
            Main.Label2.Text = "Database Offline"
        End Try
        
    End Sub
End Module
