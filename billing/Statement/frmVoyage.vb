Imports System.Data
Imports System.Data.SqlClient
Public Class frmVoyage
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmVoyage_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            Try

                myCon.open()
                myCmd = New SqlCommand(String.Format("UPDATE tbl_soa_item SET si_voyage = '{0}',si_ponum = '{1}' WHERE si_billnum = '{2}'", txtVoyage.Text, txtPOnum.Text, lblbillno.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                Me.Dispose()
                ViewStatement()
            Catch ex As Exception
                MsgBox("Please enter valid amount.")
            End Try

        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmVoyage_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class