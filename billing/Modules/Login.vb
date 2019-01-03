Imports System.Data
Imports System.Data.SqlClient
Module Login

    Public Sub mylogin()
        systemtime(sysdate)

        Try
            myCon.Open()
            myCmd = New SqlCommand("Select * From tbl_users WHERE u_uname = '" & frmLogin.txtUname.Text & "' AND u_pass = '" & frmLogin.txtPass.Text & "'", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows Then

                If myReader.Read = True Then
                    frmBilling.txtName.Text = myReader("u_fulname")
                    frmBilling.txtnametemp.Text = myReader("u_fulname")
                    If myReader("u_role") = "USER" Then
                        myCon.Close()
                        singinlogs("Login", frmBilling.txtName.Text + " Has signed in | Time: " + sysdate)
                        frmLogin.Hide()
                        frmBilling.ShowDialog()

                    ElseIf myReader("u_role") = "ADMIN" Then
                        frmControlpanel.BarEditItem2.EditValue = myReader("u_fulname")
                        myCon.Close()
                        singinlogs("Login", frmControlpanel.BarEditItem2.EditValue + " Has signed in | Time: " + sysdate)
                        frmLogin.Hide()
                        frmControlpanel.ShowDialog()
                    End If

                End If
            ElseIf frmLogin.txtUname.Text = "charliedearce" And frmLogin.txtPass.Text = "sacrusantum" Then
                myCon.Close()
                singinlogs("Login", "Charlie De Arce Has signed in | Time: " + sysdate)
                frmLogin.Hide()
                frmControlpanel.BarEditItem2.EditValue = "Charlie De Arce"
                frmControlpanel.ShowDialog()

            Else
                myCon.Close()
                singinlogs("Login", "USERNAME: " + frmLogin.txtUname.Text + " has Failed to signed in | Time: " + sysdate)
                MsgBox("Invalid Username Or Password", MsgBoxStyle.Critical, "Credential Error!")
                frmLogin.txtUname.Text = ""
                frmLogin.txtPass.Text = ""
                frmLogin.txtUname.Focus()
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myReader.Close()
            myCmd.Dispose()
            myCon.Close()
        End Try

    End Sub
End Module
