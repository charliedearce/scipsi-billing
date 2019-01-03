Imports System.Data
Imports System.Data.SqlClient
Public Class frmYtransVoyage
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmYtransVoyage_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            Try

                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_ymital_item SET yi_voyage = '" & txtVoyage.Text & "' WHERE yi_billnum = '" & lblbillno.Text & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                Me.Dispose()
                ViewYTrans()
            Catch ex As Exception
                MsgBox("Please enter valid amount.")
            End Try

        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmYtransVoyage_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class