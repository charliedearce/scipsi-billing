Imports System.Data
Imports System.Data.SqlClient
Public Class frmPartial
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
            partialbill()
            savePartial()
        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub
    Private Sub txtbillno_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtbillno.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtbillno_LostFocus(sender As Object, e As EventArgs) Handles txtbillno.LostFocus
        partialbill()
    End Sub

    Private Sub txtbillno_TextChanged(sender As Object, e As EventArgs) Handles txtbillno.TextChanged

    End Sub

    Private Sub vat_KeyPress(sender As Object, e As KeyPressEventArgs) Handles vat.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub amt_KeyPress(sender As Object, e As KeyPressEventArgs) Handles amt.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub htax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles htax.KeyPress
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

    Private Sub amt_TextChanged(sender As Object, e As EventArgs) Handles amt.TextChanged
        On Error Resume Next
        If amt.Text = "" Then
            amt.Text = 0
        End If
        txtBal.Text = amt.Text - txtNet.Text
        If Convert.ToDecimal(amt.Text) <= Convert.ToDecimal(txtNet.Text) Then
            txtindiPartial.Text = "F"
        Else
            txtindiPartial.Text = "P"
        End If
    End Sub

    Private Sub htax_TextChanged(sender As Object, e As EventArgs) Handles htax.TextChanged
        On Error Resume Next
        If htax.Text = "" Then
            htax.Text = 0
        End If
        Dim myhtax As Double = htax.Text
        Dim mynet As Double = txtNet.Text
        Dim myvat As Double

        myvat = (myhtax + mynet) / 1.12 * 0.12
        vat.Text = myvat

        If Convert.ToDecimal(amt.Text) <= (Convert.ToDecimal(txtNet.Text) + Convert.ToDecimal(htax.Text)) Then
            txtindiPartial.Text = "F"
            txtStatus.Text = "Full Payment."
        Else
            txtindiPartial.Text = "P"
            txtStatus.Text = "Partial Payment."
        End If
    End Sub

    Private Sub frmPartial_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub txtNet_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtNet.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtNet_TextChanged(sender As Object, e As EventArgs) Handles txtNet.TextChanged
        On Error Resume Next
        If txtNet.Text = "" Then
            txtNet.Text = 0
        End If
        txtBal.Text = amt.Text - txtNet.Text
        Dim myhtax As Double = htax.Text
        Dim mynet As Double = txtNet.Text
        Dim myvat As Double

        myvat = (myhtax + mynet) / 1.12 * 0.12
        vat.Text = myvat
        If Convert.ToDecimal(amt.Text) <= (Convert.ToDecimal(txtNet.Text) + Convert.ToDecimal(htax.Text)) Then
            txtindiPartial.Text = "F"
            txtStatus.Text = "Full Payment."
        Else
            txtindiPartial.Text = "P"
            txtStatus.Text = "Partial Payment."
        End If
    End Sub
End Class