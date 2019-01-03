Imports System.Data
Imports System.Data.SqlClient
Public Class frmDeposit
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmDeposit_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then

            Try
                
                myCon.open()
                myCmd = New SqlCommand("INSERT INTO tbl_orbill_trans (ot_or_no,ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_disc,ot_net) VALUES ('" & frmor.txtOrno.Text & "', 'DEPOSIT','" & vat.Text & "','" & amt.Text & "','" & htax.Text & "','" & 0 & "','" & txtTotal.Text & "')", myCon)
                myCmd.ExecuteNonQuery()
                Me.Dispose()
            Catch ex As Exception
                MsgBox("Please enter valid amount.")
            Finally
                myCon.close()
                viewORItems()
            End Try


        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmDeposit_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub vat_KeyPress(sender As Object, e As KeyPressEventArgs) Handles vat.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub vat_TextChanged(sender As Object, e As EventArgs) Handles vat.TextChanged
        If vat.Text = "" Then
            vat.Text = 0
        End If
    End Sub

    Private Sub amt_KeyPress(sender As Object, e As KeyPressEventArgs) Handles amt.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub amt_TextChanged(sender As Object, e As EventArgs) Handles amt.TextChanged
        If amt.Text = "" Then
            amt.Text = 0
        End If

    End Sub

    Private Sub htax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles htax.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub htax_TextChanged(sender As Object, e As EventArgs) Handles htax.TextChanged
        On Error Resume Next
        If htax.Text = "" Then
            htax.Text = 0
        End If


    End Sub

    Private Sub disc_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub disc_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtTotal_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtTotal.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtTotal_TextChanged(sender As Object, e As EventArgs) Handles txtTotal.TextChanged
        On Error Resume Next
        If txtTotal.Text = "" Then
            txtTotal.Text = 0
        End If
    End Sub
End Class